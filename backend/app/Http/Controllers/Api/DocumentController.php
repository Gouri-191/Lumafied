<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Fdr;
use App\Services\AuditService;
use App\Services\EnvelopeEncryptionService;
use App\Services\OcrExtractionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentController extends Controller
{
    protected EnvelopeEncryptionService $encryptionService;
    protected AuditService $auditService;
    protected OcrExtractionService $ocrService;

    public function __construct(
        EnvelopeEncryptionService $encryptionService,
        AuditService $auditService,
        OcrExtractionService $ocrService
    ) {
        $this->encryptionService = $encryptionService;
        $this->auditService = $auditService;
        $this->ocrService = $ocrService;
    }

    public function upload(Request $request, $fdrId)
    {
        $user = $request->user();
        $fdr = Fdr::findOrFail($fdrId);

        if (!$user->canAccessInstitution($fdr->institution_id)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'file' => 'required|file|max:15360|mimes:pdf,jpeg,png,jpg', // Max 15MB
            'document_type' => 'nullable|string|in:fdr_original,bank_confirmation_letter,joint_account_undertaking,renewal_receipt,release_order',
            'bank_sha256' => 'nullable|string|size:64|regex:/^[a-fA-F0-9]{64}$/',
        ]);

        $file = $request->file('file');
        $rawBytes = file_get_contents($file->getRealPath());

        // 1. Envelope Encryption (AES-256-GCM)
        $encrypted = $this->encryptionService->encryptDocument($rawBytes);
        $computedSha256 = $encrypted['sha256'];

        // Direct Bank SHA Verification on upload
        $bankProvidedSha = $request->input('bank_sha256');
        $bankShaVerified = false;
        $bankShaMatchStatus = 'not_provided';

        if ($bankProvidedSha) {
            $isExactMatch = hash_equals(strtolower($bankProvidedSha), strtolower($computedSha256));
            $bankShaVerified = $isExactMatch;
            $bankShaMatchStatus = $isExactMatch ? 'matched' : 'mismatch';

            if (!$isExactMatch) {
                $this->auditService->log('DOCUMENT_BANK_SHA_MISMATCH', 'Document', null, [
                    'fdr_id' => $fdr->id,
                    'uploaded_sha256' => $computedSha256,
                    'bank_reported_sha256' => $bankProvidedSha,
                    'status' => 'TAMPER_ALERT',
                ], 'failure');
            }
        }

        // 2. Store encrypted ciphertext in private storage
        $uuid = (string) Str::uuid();
        $storageFilename = "vault/{$fdr->institution_id}/{$uuid}.enc";
        Storage::disk('local')->put($storageFilename, $encrypted['ciphertext']);

        // 3. Persist document record with wrapped DEK, nonce, auth tag, and sha256
        $doc = Document::create([
            'uuid' => $uuid,
            'fdr_id' => $fdr->id,
            'institution_id' => $fdr->institution_id,
            'document_type' => $request->input('document_type', 'fdr_original'),
            'original_filename' => $file->getClientOriginalName(),
            'storage_path' => $storageFilename,
            'file_size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'file_hash_sha256' => $computedSha256,
            'bank_sha256' => $bankProvidedSha,
            'bank_sha_verified' => $bankShaVerified,
            'bank_sha_match_status' => $bankShaMatchStatus,
            'encryption_algorithm' => $encrypted['algorithm'],
            'encrypted_dek' => $encrypted['encrypted_dek'],
            'nonce' => $encrypted['nonce'],
            'auth_tag' => $encrypted['auth_tag'],
            'kek_version' => $encrypted['kek_version'],
            'is_quarantined' => false,
            'scan_status' => 'clean',
            'uploaded_by' => $user->id,
        ]);

        // 4. Run automated OCR extraction pipeline
        $ocr = $this->ocrService->processDocument($doc, $rawBytes);

        $this->auditService->log('UPLOAD_DOCUMENT', 'Document', (string) $doc->id, [
            'fdr_id' => $fdr->id,
            'filename' => $doc->original_filename,
            'sha256' => $doc->file_hash_sha256,
            'algorithm' => $doc->encryption_algorithm,
        ]);

        return response()->json([
            'message' => 'Document encrypted and uploaded successfully.',
            'document' => $doc,
            'ocr' => $ocr,
        ], 201);
    }

    public function download(Request $request, $id)
    {
        $user = $request->user();
        $doc = Document::findOrFail($id);

        if (!$user->canAccessInstitution($doc->institution_id)) {
            $this->auditService->log('DOCUMENT_ACCESS_DENIED', 'Document', (string) $doc->id, null, 'blocked');
            return response()->json(['message' => 'Unauthorized access to document.'], 403);
        }

        if (!Storage::disk('local')->exists($doc->storage_path)) {
            return response()->json(['message' => 'Document file not found in storage vault.'], 404);
        }

        $ciphertext = Storage::disk('local')->get($doc->storage_path);

        try {
            // Decrypt in memory with AES-256-GCM verification
            $plaintext = $this->encryptionService->decryptDocument(
                $ciphertext,
                $doc->encrypted_dek,
                $doc->nonce,
                $doc->auth_tag
            );
        } catch (RuntimeException $e) {
            $this->auditService->log('DOCUMENT_TAMPER_DETECTED', 'Document', (string) $doc->id, [
                'error' => $e->getMessage(),
            ], 'failure');

            return response()->json([
                'message' => 'SECURITY ALERT: Document decryption failed. Integrity tag mismatch or unauthorized tampering detected!',
            ], 422);
        }

        // Verify SHA-256 hash matches
        $decryptedHash = hash('sha256', $plaintext);
        if ($decryptedHash !== $doc->file_hash_sha256) {
            $this->auditService->log('DOCUMENT_HASH_MISMATCH', 'Document', (string) $doc->id, null, 'failure');
            return response()->json(['message' => 'Document integrity verification failed.'], 422);
        }

        $this->auditService->log('DOWNLOAD_DOCUMENT', 'Document', (string) $doc->id, [
            'filename' => $doc->original_filename,
        ]);

        return response($plaintext, 200, [
            'Content-Type' => $doc->mime_type,
            'Content-Disposition' => 'inline; filename="' . $doc->original_filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function verifyIntegrity(Request $request, $id)
    {
        $user = $request->user();
        $doc = Document::findOrFail($id);

        if (!$user->canAccessInstitution($doc->institution_id)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (!Storage::disk('local')->exists($doc->storage_path)) {
            return response()->json(['message' => 'File missing in vault.'], 404);
        }

        $ciphertext = Storage::disk('local')->get($doc->storage_path);

        try {
            $plaintext = $this->encryptionService->decryptDocument(
                $ciphertext,
                $doc->encrypted_dek,
                $doc->nonce,
                $doc->auth_tag
            );
            $computedHash = hash('sha256', $plaintext);
            $matches = $computedHash === $doc->file_hash_sha256;

            return response()->json([
                'status' => $matches ? 'verified_intact' : 'hash_corrupted',
                'algorithm' => $doc->encryption_algorithm,
                'recorded_sha256' => $doc->file_hash_sha256,
                'computed_sha256' => $computedHash,
                'authenticated_gcm_tag_valid' => true,
                'verified_at' => now()->toIso8601String(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'status' => 'tampered_or_invalid',
                'error' => $e->getMessage(),
                'authenticated_gcm_tag_valid' => false,
            ], 422);
        }
    }
}

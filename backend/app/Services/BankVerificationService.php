<?php

namespace App\Services;

use App\Models\Fdr;
use App\Models\BankVerificationRequest;
use App\Models\User;
use Illuminate\Support\Str;

class BankVerificationService
{
    /**
     * Dispatches an independent bank verification inquiry.
     * In demonstration mode, uses high-fidelity simulation with explicit labels.
     */
    public function initiateVerification(Fdr $fdr, User $officer, string $simulateOutcome = 'confirmed'): BankVerificationRequest
    {
        $ref = 'BNK-REQ-' . strtoupper(Str::random(10));

        // Determine simulated response payload
        $isMatch = $simulateOutcome === 'confirmed';
        $status = $isMatch ? 'confirmed' : ($simulateOutcome === 'mismatch' ? 'mismatch' : 'not_found');

        $latestDoc = $fdr->documents()->latest()->first();
        $docHash = $latestDoc?->file_hash_sha256;

        $bankReportedSha = $isMatch
            ? ($docHash ?? hash('sha256', 'BANK_GENUINE_COPY_' . $fdr->fdr_number))
            : hash('sha256', 'BANK_MISMATCHED_RECORD_' . $fdr->fdr_number);

        $shaMatches = $docHash && hash_equals(strtolower($docHash), strtolower($bankReportedSha));

        $responsePayload = [
            'gateway' => 'AICTE-BankVerification-Mock-Gateway v1.0',
            'simulated' => true,
            'disclaimer' => 'SIMULATED DEMONSTRATION DATA: Not an actual banking gateway transaction.',
            'query_timestamp' => now()->toIso8601String(),
            'queried_fdr_number' => $fdr->fdr_number,
            'queried_bank' => $fdr->bank_name,
            'queried_amount' => (float) $fdr->principal_amount,
            'bank_reported_status' => $isMatch ? 'LIVE_ACTIVE_LIEN_MARKED' : ($simulateOutcome === 'mismatch' ? 'AMOUNT_MISMATCH' : 'ACCOUNT_NOT_FOUND'),
            'bank_reported_holder' => $fdr->holder_name,
            'bank_reported_beneficiary' => 'All India Council for Technical Education (AICTE)',
            'bank_official_document_sha256' => $bankReportedSha,
            'uploaded_document_sha256' => $docHash,
            'document_sha_verified_by_bank' => $shaMatches,
            'lien_noted_in_bank_cbs' => $isMatch,
            'independent_evidence_ref' => 'CBS-CONF-' . date('Ymd') . '-' . rand(100000, 999999),
        ];

        // Update document bank SHA fields
        if ($latestDoc) {
            $latestDoc->update([
                'bank_sha256' => $bankReportedSha,
                'bank_sha_verified' => $shaMatches,
                'bank_sha_match_status' => $shaMatches ? 'matched' : 'mismatch',
            ]);
        }

        $request = BankVerificationRequest::create([
            'fdr_id' => $fdr->id,
            'request_reference' => $ref,
            'bank_name' => $fdr->bank_name,
            'mode' => 'mock',
            'status' => $status,
            'response_payload' => $responsePayload,
            'verification_evidence_ref' => $responsePayload['independent_evidence_ref'],
            'simulated_flag' => true,
            'initiated_by' => $officer->id,
            'completed_at' => now(),
        ]);

        // Update FDR verification status accordingly
        $newVerificationStatus = $isMatch ? 'verified_clean' : 'verified_with_discrepancy';
        $fdr->update(['verification_status' => $newVerificationStatus]);

        return $request;
    }
}

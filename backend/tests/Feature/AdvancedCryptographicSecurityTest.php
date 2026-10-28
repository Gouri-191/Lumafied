<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuditService;
use App\Services\EnvelopeEncryptionService;
use App\Services\Security\PepperedHashService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

class AdvancedCryptographicSecurityTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test 1: Peppered Argon2id hashing ensures password verification
     * is physically impossible without the external KMS/HSM Pepper key.
     */
    public function test_peppered_argon2id_requires_external_pepper(): void
    {
        $pepperService = new PepperedHashService();
        $password = 'SuperSecret@2026';

        // Generate Peppered Argon2id hash
        $hash = $pepperService->hash($password);

        // Verify hash contains argon2id signature and parameters
        $this->assertStringStartsWith('$argon2id$', $hash);
        $info = password_get_info($hash);
        $this->assertEquals('argon2id', $info['algoName']);
        $this->assertEquals(65536, $info['options']['memory_cost']); // 64MB memory hardness

        // Verify valid password matches
        $this->assertTrue($pepperService->verify($password, $hash));

        // Verify invalid password fails
        $this->assertFalse($pepperService->verify('WrongPassword@123', $hash));

        // Threat simulation: Attacker steals database hash and salt, but DOES NOT have the KMS pepper
        // Offline attempt with standard password_verify (without pepper) MUST fail
        $offlineWithoutPepper = password_verify($password, $hash);
        $this->assertFalse($offlineWithoutPepper, 'Attacker with stolen DB salt cannot verify password without hardware KMS pepper!');
    }

    /**
     * Test 2: Merkle Hash Chaining guarantees audit log tamper evidence.
     * Any row modification in the database is detected with mathematical certainty.
     */
    public function test_merkle_hash_chain_detects_row_tampering(): void
    {
        // Ensure clean chain for isolated tamper test
        DB::table('audit_events')->whereNotNull('chain_hash')->delete();

        $auditService = new AuditService();

        // Create 3 sequential audit logs
        $event1 = $auditService->log('FDR_SUBMISSION', 'Fdr', '101', ['amount' => 5000000]);
        $event2 = $auditService->log('VERIFICATION_STARTED', 'VerificationCase', '201', ['officer' => 'Amit']);
        $event3 = $auditService->log('APPROVAL_RECORDED', 'Fdr', '101', ['decision' => 'approved']);

        // Assert chaining structure
        $this->assertNotNull($event1->chain_hash);
        $this->assertEquals($event1->chain_hash, $event2->prev_hash);
        $this->assertEquals($event2->chain_hash, $event3->prev_hash);

        // Verification of untampered chain must pass
        $verificationBeforeTamper = $auditService->verifyAuditChain();
        $this->assertTrue($verificationBeforeTamper['valid'], json_encode($verificationBeforeTamper));
        $this->assertFalse($verificationBeforeTamper['tamper_detected']);

        // ATTACK SIMULATION: Rogue insider or hacker directly alters MySQL table
        // changing the logged action or details of Event #2
        DB::table('audit_events')
            ->where('id', $event2->id)
            ->update([
                'action' => 'UNAUTHORIZED_ALTERATION',
                'details' => json_encode(['tampered_by' => 'hacker']),
            ]);

        // Re-verify the cryptographic chain
        $verificationAfterTamper = $auditService->verifyAuditChain();

        $this->assertFalse($verificationAfterTamper['valid'], 'Tampered audit record must fail cryptographic verification!');
        $this->assertEquals($event2->id, $verificationAfterTamper['tampered_event_id']);
        $this->assertEquals('PAYLOAD_TAMPERED', $verificationAfterTamper['error_type']);

        // Clean up test events so consecutive test suites run with a clean state
        DB::table('audit_events')->whereIn('id', [$event1->id, $event2->id, $event3->id])->delete();
    }

    /**
     * Test 3: Envelope Encryption AES-256-GCM detects single-bit ciphertext tampering.
     */
    public function test_envelope_encryption_fails_on_ciphertext_tampering(): void
    {
        $encryptionService = new EnvelopeEncryptionService();
        $plaintext = 'Confidential AICTE FDR Payload: Rs 1,00,00,000 SBI Receipt';

        $encrypted = $encryptionService->encryptDocument($plaintext);

        $this->assertArrayHasKey('ciphertext', $encrypted);
        $this->assertArrayHasKey('nonce', $encrypted);
        $this->assertArrayHasKey('auth_tag', $encrypted);
        $this->assertArrayHasKey('encrypted_dek', $encrypted);

        // Legitimate decryption works
        $decrypted = $encryptionService->decryptDocument(
            $encrypted['ciphertext'],
            $encrypted['encrypted_dek'],
            $encrypted['nonce'],
            $encrypted['auth_tag']
        );
        $this->assertEquals($plaintext, $decrypted);

        // ATTACK SIMULATION: Attacker flips 1 byte in ciphertext
        $corruptedCiphertext = $encrypted['ciphertext'];
        $corruptedCiphertext[strlen($corruptedCiphertext) - 1] = chr(ord($corruptedCiphertext[strlen($corruptedCiphertext) - 1]) ^ 0x01);

        $this->expectException(\RuntimeException::class);
        $encryptionService->decryptDocument(
            $corruptedCiphertext,
            $encrypted['encrypted_dek'],
            $encrypted['nonce'],
            $encrypted['auth_tag']
        );
    }

    /**
     * Test 4: Direct Bank SHA-256 Bitwise Verification on Upload
     */
    public function test_direct_bank_sha256_verification_detects_mismatch(): void
    {
        $submitter = User::where('email', 'submitter.apex@institute.edu')->first();
        $fdr = \App\Models\Fdr::where('fdr_tracking_no', 'FDR-2026-APEX-01')->first();

        $fileContent = "%PDF-1.4 Mock AICTE FDR Receipt Content with Digital Seal";
        $actualSha256 = hash('sha256', $fileContent);

        $uploadedFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('fdr_receipt.pdf', $fileContent);

        // Case A: Upload with EXACT matching bank SHA-256
        $responseMatch = $this->actingAs($submitter, 'sanctum')
            ->post("/api/v1/fdrs/{$fdr->id}/documents", [
                'file' => $uploadedFile,
                'bank_sha256' => $actualSha256,
            ]);

        $responseMatch->assertStatus(201)
            ->assertJsonPath('document.bank_sha_verified', true)
            ->assertJsonPath('document.bank_sha_match_status', 'matched');

        $docId = $responseMatch->json('document.id');

        // Case B: Upload with MISMATCHED bank SHA-256 (e.g. forged PDF)
        $fakeBankSha = hash('sha256', 'DIFFERENT_BANK_RECORD_BYTES');
        $uploadedFileMismatch = \Illuminate\Http\UploadedFile::fake()->createWithContent('fdr_altered.pdf', $fileContent);

        $responseMismatch = $this->actingAs($submitter, 'sanctum')
            ->post("/api/v1/fdrs/{$fdr->id}/documents", [
                'file' => $uploadedFileMismatch,
                'bank_sha256' => $fakeBankSha,
            ]);

        $responseMismatch->assertStatus(201)
            ->assertJsonPath('document.bank_sha_verified', false)
            ->assertJsonPath('document.bank_sha_match_status', 'mismatch');

        $mismatchDocId = $responseMismatch->json('document.id');

        // Verify Discrepancy Detection Engine catches the mismatch
        $engine = new \App\Services\DiscrepancyDetectionEngine();
        $run = $engine->validateFdr($fdr);

        $discrepancy = $run->discrepancies()->where('rule_identifier', 'BANK_SHA256_MISMATCH')->first();
        $this->assertNotNull($discrepancy);
        $this->assertEquals('verification_blocked', $discrepancy->severity);

        // Clean up created docs and discrepancies so test leaves zero footprint
        \App\Models\Document::whereIn('id', [$docId, $mismatchDocId])->delete();
        $run->discrepancies()->delete();
        $run->delete();
    }

    /**
     * Test 5: Verify decrypted downloaded FDR documents are 100% valid PDF files
     */
    public function test_downloaded_fdr_documents_are_valid_pdf_binaries(): void
    {
        $admin = User::where('email', 'admin@aicte-india.org')->first();
        $doc = \App\Models\Document::first();

        $response = $this->actingAs($admin, 'sanctum')
            ->get("/api/v1/documents/{$doc->id}/download");

        $response->assertStatus(200);
        $content = $response->getContent();

        // Must be a valid PDF-1.4 file
        $this->assertStringStartsWith('%PDF-1.4', $content);
        $this->assertStringEndsWith("%%EOF\n", $content);
        $this->assertStringContainsString('FDR CERTIFICATE NO:', $content);
    }

    /**
     * Test 6: AI/OCR pre-submission scan auto-detects bank, branch, IFSC, amount, and dates
     */
    public function test_ocr_scan_preview_autodetects_bank_receipt_fields(): void
    {
        $admin = User::where('email', 'admin@aicte-india.org')->first();

        $pdfGenerator = new \App\Services\PdfGeneratorService();
        $pdfContent = $pdfGenerator->generateFdrReceiptPdf(
            'Canara Bank',
            'Koramangala 4th Block Branch',
            'CNRB0001420',
            'CNRB-FDR-88990011',
            'Bangalore Institute of Technology',
            'AICTE & Bangalore Joint A/C',
            6500000.00,
            '2024-02-15',
            '2034-02-15',
            7.35,
            120
        );

        $tempPath = tempnam(sys_get_temp_dir(), 'fdr_scan_');
        file_put_contents($tempPath, $pdfContent);

        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $tempPath,
            'canara_fdr_receipt.pdf',
            'application/pdf',
            null,
            true
        );

        $response = $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/ocr/scan-preview', [
                'file' => $uploadedFile,
            ]);

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertTrue($json['success']);
        $this->assertEquals(hash('sha256', $pdfContent), $json['sha256']);
        $this->assertGreaterThanOrEqual(8, $json['fields_detected_count']);
        $this->assertEquals('Canara Bank', $json['detected_fields']['bank_name']);
        $this->assertEquals('Koramangala 4th Block Branch', $json['detected_fields']['branch_name']);
        $this->assertEquals('CNRB0001420', $json['detected_fields']['ifsc_code']);
        $this->assertEquals('CNRB-FDR-88990011', $json['detected_fields']['fdr_number']);
        $this->assertEquals(6500000.00, $json['detected_fields']['principal_amount']);
        $this->assertEquals(7.35, $json['detected_fields']['interest_rate']);
        $this->assertEquals('2024-02-15', $json['detected_fields']['issue_date']);
        $this->assertEquals('2034-02-15', $json['detected_fields']['maturity_date']);

        @unlink($tempPath);
    }

    /**
     * Test 7: AI/OCR parses CMap TrueType font PDF (real world Canara Bank receipt)
     */
    public function test_ocr_scan_preview_with_cmap_encoded_pdf_from_real_world(): void
    {
        $admin = User::where('email', 'admin@aicte-india.org')->first();

        $realFile = 'C:/Users/Vishal/Downloads/canara_receipt_sample.pdf';
        if (file_exists($realFile)) {
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $realFile,
                'canara_receipt_sample.pdf',
                'application/pdf',
                null,
                true
            );

            $response = $this->actingAs($admin, 'sanctum')
                ->post('/api/v1/ocr/scan-preview', [
                    'file' => $uploadedFile,
                ]);

            $response->assertStatus(200);
            $json = $response->json();

            $this->assertTrue($json['success']);
            $this->assertEquals('Canara Bank', $json['detected_fields']['bank_name']);
            $this->assertStringContainsString('SHIVAJINAGAR', $json['detected_fields']['branch_name']);
            $this->assertEquals('CNRB0002123', $json['detected_fields']['ifsc_code']);
            $this->assertEquals('CANARA-FDR-2016-887711', $json['detected_fields']['fdr_number']);
            $this->assertEquals(2500000.00, $json['detected_fields']['principal_amount']);
            $this->assertEquals(7.5, $json['detected_fields']['interest_rate']);
            $this->assertEquals('2018-12-25', $json['detected_fields']['issue_date']);
            $this->assertEquals('2026-12-25', $json['detected_fields']['maturity_date']);
        }
    }
}

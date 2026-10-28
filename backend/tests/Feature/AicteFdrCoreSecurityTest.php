<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Institution;
use App\Models\Fdr;
use App\Models\Document;
use App\Services\EnvelopeEncryptionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;

class AicteFdrCoreSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected EnvelopeEncryptionService $encryptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encryptionService = new EnvelopeEncryptionService();
    }

    /**
     * Test 1: Authentication & Token Issuance
     */
    public function test_user_authentication_and_token_issuance(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@aicte-india.org',
            'password' => 'Password@123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);

        // Unauthenticated access rejected
        $this->getJson('/api/v1/auth/me')->assertStatus(401);

        // Authenticated access succeeds
        $token = $response->json('token');
        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('user.email', 'admin@aicte-india.org');
    }

    /**
     * Test 2: Tenant Isolation — Institute A cannot access Institute B records
     */
    public function test_cross_tenant_institution_isolation(): void
    {
        $apexSubmitter = User::where('email', 'submitter.apex@institute.edu')->first();
        $bharatFdr = Fdr::where('fdr_tracking_no', 'FDR-2026-BHARAT-01')->first();

        // Apex user tries to view Bharat FDR
        $response = $this->actingAs($apexSubmitter, 'sanctum')
            ->getJson("/api/v1/fdrs/{$bharatFdr->id}");

        $response->assertStatus(403);
    }

    /**
     * Test 3: Unauthorized Document Download is Rejected
     */
    public function test_unauthorized_document_download_rejected(): void
    {
        $bharatSubmitter = User::where('email', 'submitter.bharat@institute.edu')->first();
        $apexFdr = Fdr::where('fdr_tracking_no', 'FDR-2026-APEX-01')->first();
        $apexDoc = $apexFdr->documents()->first();

        $response = $this->actingAs($bharatSubmitter, 'sanctum')
            ->getJson("/api/v1/documents/{$apexDoc->id}/download");

        $response->assertStatus(403);
    }

    /**
     * Test 4: Separation of Duties — Submitter cannot approve own FDR
     */
    public function test_separation_of_duties_prevents_submitter_self_approval(): void
    {
        $apexFdr = Fdr::where('fdr_tracking_no', 'FDR-2026-APEX-02')->first();
        $apexSubmitter = User::find($apexFdr->created_by);
        $case = $apexFdr->verificationCase;

        // Submitter attempts to approve
        $response = $this->actingAs($apexSubmitter, 'sanctum')
            ->postJson("/api/v1/verification-cases/{$case->id}/approve", [
                'comments' => 'Self approving my own FDR',
            ]);

        // Must fail with 422 Separation of Duties Violation or 403 Forbidden
        $this->assertTrue(in_array($response->status(), [403, 422], true));
    }

    /**
     * Test 5: Envelope Encryption (AES-256-GCM) & Tamper Detection
     */
    public function test_envelope_encryption_detects_ciphertext_tampering(): void
    {
        $originalText = "CONFIDENTIAL FDR VOUCHER DATA - INR 50,00,000";
        $encrypted = $this->encryptionService->encryptDocument($originalText);

        // Decryption of clean ciphertext succeeds
        $decrypted = $this->encryptionService->decryptDocument(
            $encrypted['ciphertext'],
            $encrypted['encrypted_dek'],
            $encrypted['nonce'],
            $encrypted['auth_tag']
        );
        $this->assertEquals($originalText, $decrypted);

        // Tamper with ciphertext by corrupting 1 byte
        $tamperedCiphertext = $encrypted['ciphertext'];
        $tamperedCiphertext[5] = chr(ord($tamperedCiphertext[5]) ^ 0xFF);

        // Decryption MUST fail with RuntimeException
        $this->expectException(RuntimeException::class);
        $this->encryptionService->decryptDocument(
            $tamperedCiphertext,
            $encrypted['encrypted_dek'],
            $encrypted['nonce'],
            $encrypted['auth_tag']
        );
    }

    /**
     * Test 6: Automated Discrepancy Detection Engine
     */
    public function test_discrepancy_detection_identifies_financial_mismatch(): void
    {
        $fdr2 = Fdr::where('fdr_tracking_no', 'FDR-2026-APEX-02')->first();
        $discrepancy = $fdr2->discrepancies()->where('rule_identifier', 'AMOUNT_MISMATCH')->first();

        $this->assertNotNull($discrepancy);
        $this->assertEquals('high_priority', $discrepancy->severity);
        $this->assertEquals('principal_amount', $discrepancy->field_name);
    }

    /**
     * Test 7: Simulated Bank Verification Declaration
     */
    public function test_bank_verification_declares_simulation_flag(): void
    {
        $officer = User::where('email', 'officer@aicte-india.org')->first();
        $fdr = Fdr::where('fdr_tracking_no', 'FDR-2026-APEX-01')->first();

        $response = $this->actingAs($officer, 'sanctum')
            ->postJson("/api/v1/fdrs/{$fdr->id}/bank-verification", [
                'simulate_outcome' => 'confirmed',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('bank_verification.simulated_flag', true)
            ->assertJsonPath('bank_verification.mode', 'mock');
    }

    /**
     * Test 8: Rejection of Formula Injection and Command Injection Payloads
     */
    public function test_fdr_creation_rejects_formula_and_command_injection(): void
    {
        $submitter = User::where('email', 'submitter.apex@institute.edu')->first();

        $payload = [
            'bank_name' => '=cmd|"/C calc"!A0',
            'branch_name' => 'Main Branch',
            'ifsc_code' => 'SBIN0001234',
            'fdr_number' => '@SUM(1,1)',
            'principal_amount' => 500000,
            'issue_date' => '2026-01-01',
            'maturity_date' => '2036-01-01',
            'tenure_months' => 120,
            'holder_name' => 'Apex College',
            'beneficiary_name' => 'AICTE Joint A/C',
        ];

        $response = $this->actingAs($submitter, 'sanctum')
            ->postJson('/api/v1/fdrs', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bank_name', 'fdr_number']);
    }
}

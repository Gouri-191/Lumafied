<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Institution;
use App\Models\Fdr;
use App\Models\Document;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AicteFdrLifecycleAndComplianceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\DB::table('fdrs')->where('id', '>', 5)->delete();
        parent::tearDown();
    }

    /**
     * Test: Submitting an FDR without uploaded document must fail
     */
    public function test_cannot_submit_fdr_without_supporting_document(): void
    {
        $apexSubmitter = User::where('email', 'submitter.apex@institute.edu')->first();
        $uniqueSuffix = uniqid();

        // Create bare draft without document
        $fdr = Fdr::create([
            'fdr_tracking_no' => 'FDR-TEST-NODOC-' . $uniqueSuffix,
            'institution_id' => $apexSubmitter->institution_id,
            'bank_name' => 'State Bank of India',
            'branch_name' => 'Branch Test',
            'fdr_number' => 'NODOC-' . $uniqueSuffix,
            'principal_amount' => 1000000.00,
            'issue_date' => '2026-01-01',
            'maturity_date' => '2036-01-01',
            'holder_name' => 'Apex Test',
            'beneficiary_name' => 'AICTE Joint A/C',
            'lifecycle_status' => 'draft',
            'created_by' => $apexSubmitter->id,
        ]);

        $response = $this->actingAs($apexSubmitter, 'sanctum')
            ->postJson("/api/v1/fdrs/{$fdr->id}/submit");

        $fdr->delete();

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot submit FDR without uploading the original Fixed Deposit Receipt document.');
    }

    /**
     * Test: AICTE Admin can register an Institution, but regular Submitter cannot
     */
    public function test_institution_registration_restricted_to_admin(): void
    {
        $submitter = User::where('email', 'submitter.apex@institute.edu')->first();
        $admin = User::where('email', 'admin@aicte-india.org')->first();
        $uniqueAicteId = '1-' . rand(100000000, 999999999);

        $payload = [
            'aicte_id' => $uniqueAicteId,
            'name' => 'Govt Engineering College Testing ' . $uniqueAicteId,
            'category' => 'Engineering & Technology',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'pincode' => '411001',
            'contact_email' => 'principal@gecpune.edu.in',
        ];

        // Submitter forbidden
        $this->actingAs($submitter, 'sanctum')
            ->postJson('/api/v1/institutions', $payload)
            ->assertStatus(403);

        // Admin succeeds
        $res = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/institutions', $payload);

        Institution::where('aicte_id', $uniqueAicteId)->delete();

        $res->assertStatus(201)
            ->assertJsonPath('aicte_id', $uniqueAicteId);
    }

    /**
     * Test: CSV Export provides formula injection sanitization
     */
    public function test_csv_export_sanitizes_formula_injection(): void
    {
        $admin = User::where('email', 'admin@aicte-india.org')->first();
        $uniqueSuffix = uniqid();

        // Create FDR with malicious title starting with '='
        $maliciousFdr = Fdr::create([
            'fdr_tracking_no' => 'FDR-MALICIOUS-' . $uniqueSuffix,
            'institution_id' => 1,
            'bank_name' => '=cmd|"/C calc"!A0',
            'branch_name' => '+cmd|"/C notepad"!A0',
            'fdr_number' => '@SUM(1,1)',
            'principal_amount' => 500000.00,
            'issue_date' => '2026-01-01',
            'maturity_date' => '2036-01-01',
            'holder_name' => 'Malicious Submitter',
            'beneficiary_name' => 'AICTE Joint A/C',
            'lifecycle_status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->get('/api/v1/reports/export-csv');

        // Immediately delete malicious record so it never leaks into DB
        $maliciousFdr->delete();

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // Verify malicious characters were prepended with single quote
        $this->assertStringContainsString("'=cmd", $content);
        $this->assertStringContainsString("'+cmd", $content);
        $this->assertStringContainsString("'@SUM", $content);
    }
}

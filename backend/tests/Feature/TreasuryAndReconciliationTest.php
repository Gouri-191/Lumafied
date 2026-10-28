<?php

namespace Tests\Feature;

use App\Models\Fdr;
use App\Models\FdrRenewal;
use App\Models\FdrReminder;
use App\Models\Institution;
use App\Models\ReconciliationRun;
use App\Models\User;
use App\Services\RenewalPlanningService;
use App\Services\ReconciliationService;
use App\Services\ReminderService;
use App\Services\TreasuryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TreasuryAndReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected Institution $institution;

    protected function setUp(): void
    {
        parent::setUp();

        Fdr::where('fdr_tracking_no', 'FDR-2026-BHARAT-01')->update([
            'lifecycle_status' => 'maturing',
            'renewal_status' => 'requested',
            'version' => 1,
        ]);

        $this->adminUser = User::where('role', 'system_admin')->first();
        $this->institution = Institution::first();
    }

    protected function tearDown(): void
    {
        Fdr::whereNotNull('parent_fdr_id')->delete();
        Fdr::where('fdr_tracking_no', 'FDR-2026-BHARAT-01')->update([
            'lifecycle_status' => 'maturing',
            'renewal_status' => 'requested',
            'version' => 1,
        ]);

        parent::tearDown();
    }

    public function test_treasury_metrics_endpoint_returns_separated_financial_measures()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/treasury/metrics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_recorded_principal',
                    'outstanding_active_principal',
                    'expected_interest',
                    'expected_maturity_proceeds',
                    'confirmed_maturity_proceeds',
                    'released_funds',
                    'encumbered_principal',
                    'unencumbered_principal',
                    'weighted_average_rate',
                    'total_active_count',
                    'high_risk_count',
                ]
            ]);

        $data = $response->json('data');

        // Verify mathematical non-equivalence of distinct financial measures
        $this->assertGreaterThan(0, $data['total_recorded_principal']);
        $this->assertGreaterThan(0, $data['expected_maturity_proceeds']);
        $this->assertGreaterThan(0, $data['weighted_average_rate']);
        // Maturity proceeds must be strictly greater than principal
        $this->assertGreaterThan($data['outstanding_active_principal'], $data['expected_maturity_proceeds']);
    }

    public function test_maturity_ladder_endpoint_categorizes_buckets()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/treasury/maturity-ladder');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'overdue',
                    '0_30',
                    '31_60',
                    '61_90',
                    '91_180',
                    '181_365',
                    'over_365',
                ]
            ]);

        // FDR 3 was seeded to mature in 24 days -> should be in 0_30 bucket
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, $data['0_30']['count']);
    }

    public function test_reconciliation_import_detects_clean_matches_and_amount_mismatches()
    {
        $fdr1 = Fdr::where('fdr_number', 'SBI-FDR-9876543210')->first();
        $this->assertNotNull($fdr1);

        $payload = [
            'bank_name' => 'State Bank of India',
            'entries' => [
                [
                    'fdr_number' => 'SBI-FDR-9876543210',
                    'principal_amount' => 5000000.00, // Exact match
                    'account_number' => 'A/C-XXXX-XXXX-4501',
                    'transaction_date' => '2026-01-10',
                ],
                [
                    'fdr_number' => 'SBI-FDR-9876543210',
                    'principal_amount' => 4200000.00, // Mismatch (8 Lakhs variance)
                    'account_number' => 'A/C-XXXX-XXXX-9911',
                    'transaction_date' => '2026-01-10',
                ],
                [
                    'fdr_number' => 'SBI-NONEXISTENT-999',
                    'principal_amount' => 1500000.00, // Unmatched
                    'account_number' => 'A/C-XXXX-XXXX-0000',
                    'transaction_date' => '2026-01-10',
                ],
            ],
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/reconciliation/import', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'bank_name' => 'State Bank of India',
                    'matched_count' => 1,
                    'discrepancy_count' => 2,
                ]
            ]);
    }

    public function test_renewal_scenario_calculation_and_approval_spawns_linked_version()
    {
        $fdr = Fdr::where('lifecycle_status', 'maturing')->first();
        $this->assertNotNull($fdr);

        // 1. Get scenarios
        $scenarioResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/renewals/scenarios/{$fdr->id}");

        $scenarioResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'fdr',
                    'options' => [
                        'option_a',
                        'option_b',
                        'option_c',
                    ]
                ]
            ]);

        // 2. Request renewal
        $requestPayload = [
            'renewal_option' => 'option_b',
            'proposed_principal' => $fdr->principal_amount,
            'proposed_rate' => 7.50,
            'proposed_tenure_months' => 36,
            'estimated_maturity_amount' => 4950000.00,
            'reason' => 'Testing scenario approval and version linking',
        ];

        $requestResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/renewals/request/{$fdr->id}", $requestPayload);

        $requestResponse->assertStatus(201);
        $renewalId = $requestResponse->json('data.id');

        // 3. Approve renewal as AICTE admin
        $approveResponse = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/renewals/{$renewalId}/approve", [
                'decision_notes' => 'Approved under test verification'
            ]);

        $approveResponse->assertStatus(200);

        // Verify linked new FDR version created
        $newFdrData = $approveResponse->json('data.new_fdr');
        $this->assertEquals($fdr->id, $newFdrData['parent_fdr_id']);
        $this->assertEquals($fdr->version + 1, $newFdrData['version']);
        $this->assertEquals('active', $newFdrData['lifecycle_status']);

        // Verify original FDR transitioned to renewed, preserving history
        $fdr->refresh();
        $this->assertEquals('renewed', $fdr->lifecycle_status);
        $this->assertEquals('completed', $fdr->renewal_status);
    }

    public function test_reminder_trigger_check_generates_and_escalates_reminders()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/reminders/trigger');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'new_reminders_generated',
                    'escalated_to_supervisors',
                ]
            ]);
    }
}

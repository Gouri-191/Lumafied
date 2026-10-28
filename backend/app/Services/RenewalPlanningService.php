<?php

namespace App\Services;

use App\Models\Fdr;
use App\Models\FdrRenewal;
use App\Models\FdrStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class RenewalPlanningService
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Calculate 3 actionable scenario options for an FDR nearing or at maturity.
     */
    public function calculateScenarioOptions(Fdr $fdr): array
    {
        $p = (float) $fdr->principal_amount;
        $r = (float) $fdr->interest_rate;
        $t = (int) ($fdr->tenure_months ?: 12);
        $expectedInterest = (float) $fdr->expected_interest_amount;
        $compoundProceeds = (float) $fdr->expected_maturity_amount;

        // Option A: Rollover Principal + Interest at current rate
        $reinvestedPrincipalA = $compoundProceeds > 0 ? $compoundProceeds : ($p + $expectedInterest);
        $optionAInterest = $this->calculateCompoundInterest($reinvestedPrincipalA, $r, $t);
        $optionAMaturity = $reinvestedPrincipalA + $optionAInterest;

        // Option B: Long-term Lock (36 months at +0.60% premium yield)
        $tenureB = 36;
        $rateB = round($r + 0.60, 2);
        $optionBInterest = $this->calculateCompoundInterest($reinvestedPrincipalA, $rateB, $tenureB);
        $optionBMaturity = $reinvestedPrincipalA + $optionBInterest;

        // Option C: Release & Liquidation
        $releasedNet = $reinvestedPrincipalA;

        return [
            'fdr' => [
                'id' => $fdr->id,
                'fdr_number' => $fdr->fdr_number,
                'bank_name' => $fdr->bank_name,
                'principal_amount' => $p,
                'interest_rate' => $r,
                'tenure_months' => $t,
                'maturity_date' => $fdr->maturity_date ? $fdr->maturity_date->toDateString() : null,
                'encumbrance_status' => $fdr->encumbrance_status,
            ],
            'options' => [
                'option_a' => [
                    'title' => 'Standard Term Rollover (Same Tenure)',
                    'description' => "Reinvest full maturity proceeds for {$t} months at prevailing benchmark rate {$r}%.",
                    'proposed_principal' => round($reinvestedPrincipalA, 2),
                    'proposed_rate' => $r,
                    'proposed_tenure_months' => $t,
                    'estimated_interest' => round($optionAInterest, 2),
                    'estimated_maturity_amount' => round($optionAMaturity, 2),
                    'action_type' => 'RENEW_STANDARD',
                ],
                'option_b' => [
                    'title' => 'Multi-Year Long Term Optimization (+0.60% Yield)',
                    'description' => "Reinvest for 36 months under institutional tier pricing at {$rateB}% p.a. to maximize return.",
                    'proposed_principal' => round($reinvestedPrincipalA, 2),
                    'proposed_rate' => $rateB,
                    'proposed_tenure_months' => $tenureB,
                    'estimated_interest' => round($optionBInterest, 2),
                    'estimated_maturity_amount' => round($optionBMaturity, 2),
                    'action_type' => 'RENEW_EXTENDED',
                ],
                'option_c' => [
                    'title' => 'Release & Liquidation to Operating Treasury',
                    'description' => 'Discharge regulatory lien, redeem funds with accrued interest, and credit institution account.',
                    'proposed_principal' => 0.00,
                    'proposed_rate' => 0.00,
                    'proposed_tenure_months' => 0,
                    'estimated_interest' => 0.00,
                    'estimated_maturity_amount' => round($releasedNet, 2),
                    'action_type' => 'RELEASE_FUNDS',
                ],
            ],
        ];
    }

    /**
     * Submit an FDR renewal request.
     */
    public function requestRenewal(Fdr $fdr, array $data, User $user): FdrRenewal
    {
        $renewalRef = 'RNW-' . strtoupper(Str::random(6)) . '-' . date('Ymd');
        $proposedPrincipal = (float) ($data['proposed_principal'] ?? $fdr->principal_amount);
        $proposedRate = (float) ($data['proposed_rate'] ?? $fdr->interest_rate);
        $proposedTenure = (int) ($data['proposed_tenure_months'] ?? $fdr->tenure_months);
        $estimatedMaturity = (float) ($data['estimated_maturity_amount'] ?? ($proposedPrincipal * 1.07));

        $renewal = FdrRenewal::create([
            'request_reference' => $renewalRef,
            'original_fdr_id' => $fdr->id,
            'institution_id' => $fdr->institution_id,
            'proposed_tenure_months' => $proposedTenure,
            'proposed_interest_rate' => $proposedRate,
            'proposed_interest_type' => 'cumulative',
            'estimated_new_maturity_amount' => $estimatedMaturity,
            'reason' => $data['reason'] ?? 'Statutory periodic renewal under AICTE guidelines.',
            'status' => 'pending_review',
            'requested_by' => $user->id,
        ]);

        $fdr->update(['renewal_status' => 'requested']);

        $this->auditService->log(
            'FDR_RENEWAL_REQUESTED',
            'fdr_renewals',
            (string) $renewal->id,
            [
                'original_fdr_id' => $fdr->id,
                'previous_principal' => $fdr->principal_amount,
                'status' => 'pending_review',
                'reference' => $renewalRef,
                'estimated_new_maturity' => $estimatedMaturity,
            ],
            'success',
            $user
        );

        return $renewal;
    }

    /**
     * Approve renewal request, spawn linked child FDR version, and preserve immutable history.
     */
    public function approveRenewal(FdrRenewal $renewal, User $authority, string $notes = ''): Fdr
    {
        $originalFdr = $renewal->originalFdr;

        $issueDate = $originalFdr->maturity_date ?: Carbon::today();
        $maturityDate = $issueDate->copy()->addMonths($renewal->proposed_tenure_months);

        // Spawn linked new FDR version
        $newTrackingNo = 'AICTE-FDR-' . date('Y') . '-' . strtoupper(Str::random(6));
        $newFdr = Fdr::create([
            'fdr_tracking_no' => $newTrackingNo,
            'institution_id' => $originalFdr->institution_id,
            'bank_name' => $originalFdr->bank_name,
            'branch_name' => $originalFdr->branch_name,
            'ifsc_code' => $originalFdr->ifsc_code,
            'fdr_number' => $originalFdr->fdr_number . '-R' . ($originalFdr->version + 1),
            'account_number_ref' => $originalFdr->account_number_ref,
            'principal_amount' => $originalFdr->principal_amount,
            'currency' => $originalFdr->currency ?: 'INR',
            'interest_rate' => $renewal->proposed_interest_rate,
            'issue_date' => $issueDate,
            'maturity_date' => $maturityDate,
            'tenure_months' => $renewal->proposed_tenure_months,
            'holder_name' => $originalFdr->holder_name,
            'beneficiary_name' => $originalFdr->beneficiary_name,
            'purpose' => $originalFdr->purpose,
            'lifecycle_status' => 'active',
            'verification_status' => 'verified_clean',
            'risk_level' => 'low',
            'interest_type' => 'cumulative',
            'expected_interest_amount' => round($renewal->estimated_new_maturity_amount - $originalFdr->principal_amount, 2),
            'expected_maturity_amount' => $renewal->estimated_new_maturity_amount,
            'encumbrance_status' => $originalFdr->encumbrance_status,
            'renewal_status' => 'none',
            'parent_fdr_id' => $originalFdr->id,
            'version' => $originalFdr->version + 1,
            'created_by' => $authority->id,
            'last_updated_by' => $authority->id,
        ]);

        // Transition original FDR to 'renewed'
        $originalFdr->update([
            'lifecycle_status' => 'renewed',
            'renewal_status' => 'completed',
            'last_updated_by' => $authority->id,
        ]);

        FdrStatusHistory::create([
            'fdr_id' => $originalFdr->id,
            'from_status' => 'active',
            'to_status' => 'renewed',
            'actor_id' => $authority->id,
            'action' => 'RENEWAL_APPROVED',
            'comments' => "Renewed into version {$newFdr->version} ({$newFdr->fdr_tracking_no}).",
        ]);

        // Update renewal record
        $renewal->update([
            'status' => 'approved',
            'decision_notes' => $notes,
            'approved_by' => $authority->id,
            'effective_date' => Carbon::today(),
            'new_fdr_id' => $newFdr->id,
        ]);

        $this->auditService->log(
            'FDR_RENEWAL_APPROVED',
            'fdrs',
            (string) $newFdr->id,
            [
                'original_fdr_id' => $originalFdr->id,
                'new_fdr_id' => $newFdr->id,
                'version' => $newFdr->version,
                'approval_notes' => $notes,
            ],
            'success',
            $authority
        );

        return $newFdr;
    }

    protected function calculateCompoundInterest(float $principal, float $rate, int $tenureMonths): float
    {
        $years = $tenureMonths / 12.0;
        $amount = $principal * pow((1 + ($rate / 400.0)), 4 * $years);
        return $amount - $principal;
    }
}

<?php

namespace App\Services;

use App\Models\Fdr;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    /**
     * Compute comprehensive 7-measure treasury metrics with strict separation of funds.
     */
    public function getTreasuryMetrics(?int $institutionId = null): array
    {
        $query = Fdr::query();
        if ($institutionId) {
            $query->where('institution_id', $institutionId);
        }

        $allFdrs = $query->get();

        $activeStatuses = ['active', 'submitted', 'verified', 'approved', 'maturing'];
        $activeFdrs = $allFdrs->whereIn('lifecycle_status', $activeStatuses);

        $totalRecordedPrincipal = (float) $allFdrs->sum('principal_amount');
        $outstandingActivePrincipal = (float) $activeFdrs->sum('principal_amount');
        $expectedInterest = (float) $activeFdrs->sum('expected_interest_amount');
        $expectedMaturityProceeds = (float) $activeFdrs->sum('expected_maturity_amount');
        $confirmedMaturityProceeds = (float) $allFdrs->sum('confirmed_maturity_amount');
        $releasedFunds = (float) $allFdrs->where('lifecycle_status', 'released')->sum('released_amount');

        $encumberedPrincipal = (float) $activeFdrs->where('encumbrance_status', 'lien_marked')->sum('principal_amount');
        $unencumberedPrincipal = (float) $activeFdrs->where('encumbrance_status', 'unencumbered')->sum('principal_amount');

        // Weighted Average Interest Rate: sum(P_i * R_i) / sum(P_i)
        $rateWeightProductSum = 0.0;
        $activePrincipalSumForRates = 0.0;
        foreach ($activeFdrs as $fdr) {
            $p = (float) $fdr->principal_amount;
            $r = (float) $fdr->interest_rate;
            if ($p > 0 && $r > 0) {
                $rateWeightProductSum += ($p * $r);
                $activePrincipalSumForRates += $p;
            }
        }

        $weightedAverageRate = $activePrincipalSumForRates > 0
            ? round($rateWeightProductSum / $activePrincipalSumForRates, 2)
            : 0.00;

        return [
            'total_recorded_principal' => round($totalRecordedPrincipal, 2),
            'outstanding_active_principal' => round($outstandingActivePrincipal, 2),
            'expected_interest' => round($expectedInterest, 2),
            'expected_maturity_proceeds' => round($expectedMaturityProceeds, 2),
            'confirmed_maturity_proceeds' => round($confirmedMaturityProceeds, 2),
            'released_funds' => round($releasedFunds, 2),
            'encumbered_principal' => round($encumberedPrincipal, 2),
            'unencumbered_principal' => round($unencumberedPrincipal, 2),
            'weighted_average_rate' => $weightedAverageRate,
            'total_active_count' => $activeFdrs->count(),
            'high_risk_count' => $activeFdrs->whereIn('risk_level', ['high', 'critical'])->count(),
        ];
    }

    /**
     * Group FDRs into liquidity maturity ladder buckets.
     */
    public function getMaturityLadder(?int $institutionId = null): array
    {
        $today = Carbon::today();
        $query = Fdr::whereIn('lifecycle_status', ['active', 'submitted', 'verified', 'approved', 'maturing']);
        if ($institutionId) {
            $query->where('institution_id', $institutionId);
        }

        $fdrs = $query->with('institution')->get();

        $buckets = [
            'overdue' => ['label' => 'Overdue / Past Maturity', 'min' => -9999, 'max' => -1, 'count' => 0, 'principal' => 0.0, 'items' => []],
            '0_30' => ['label' => '0 - 30 Days (Immediate)', 'min' => 0, 'max' => 30, 'count' => 0, 'principal' => 0.0, 'items' => []],
            '31_60' => ['label' => '31 - 60 Days (Short Term)', 'min' => 31, 'max' => 60, 'count' => 0, 'principal' => 0.0, 'items' => []],
            '61_90' => ['label' => '61 - 90 Days (Medium Term)', 'min' => 61, 'max' => 90, 'count' => 0, 'principal' => 0.0, 'items' => []],
            '91_180' => ['label' => '91 - 180 Days (Half Year)', 'min' => 91, 'max' => 180, 'count' => 0, 'principal' => 0.0, 'items' => []],
            '181_365' => ['label' => '181 - 365 Days (Annual)', 'min' => 181, 'max' => 365, 'count' => 0, 'principal' => 0.0, 'items' => []],
            'over_365' => ['label' => '> 365 Days (Long Term)', 'min' => 366, 'max' => 99999, 'count' => 0, 'principal' => 0.0, 'items' => []],
        ];

        foreach ($fdrs as $fdr) {
            $matDate = Carbon::parse($fdr->maturity_date);
            $diffDays = (int) $today->diffInDays($matDate, false);

            $matchedKey = 'over_365';
            if ($diffDays < 0) {
                $matchedKey = 'overdue';
            } else {
                foreach ($buckets as $key => $bucket) {
                    if ($key === 'overdue') continue;
                    if ($diffDays >= $bucket['min'] && $diffDays <= $bucket['max']) {
                        $matchedKey = $key;
                        break;
                    }
                }
            }

            $buckets[$matchedKey]['count']++;
            $buckets[$matchedKey]['principal'] += (float) $fdr->principal_amount;
            $buckets[$matchedKey]['items'][] = [
                'id' => $fdr->id,
                'fdr_number' => $fdr->fdr_number,
                'bank_name' => $fdr->bank_name,
                'institution' => $fdr->institution ? $fdr->institution->name : 'N/A',
                'principal_amount' => (float) $fdr->principal_amount,
                'maturity_date' => $fdr->maturity_date->toDateString(),
                'days_remaining' => $diffDays,
                'interest_rate' => (float) $fdr->interest_rate,
                'expected_maturity_amount' => (float) $fdr->expected_maturity_amount,
            ];
        }

        foreach ($buckets as &$b) {
            $b['principal'] = round($b['principal'], 2);
        }

        return $buckets;
    }

    /**
     * Bank concentration analytics.
     */
    public function getBankConcentration(?int $institutionId = null): array
    {
        $query = Fdr::whereIn('lifecycle_status', ['active', 'submitted', 'verified', 'approved', 'maturing']);
        if ($institutionId) {
            $query->where('institution_id', $institutionId);
        }

        $totalActivePrincipal = (float) $query->sum('principal_amount');

        $groups = $query->select(
            'bank_name',
            DB::raw('COUNT(*) as fdr_count'),
            DB::raw('SUM(principal_amount) as total_principal'),
            DB::raw('AVG(interest_rate) as avg_interest_rate')
        )->groupBy('bank_name')->get();

        $result = [];
        foreach ($groups as $g) {
            $princ = (float) $g->total_principal;
            $share = $totalActivePrincipal > 0 ? round(($princ / $totalActivePrincipal) * 100, 2) : 0;

            $result[] = [
                'bank_name' => $g->bank_name,
                'fdr_count' => (int) $g->fdr_count,
                'total_principal' => round($princ, 2),
                'portfolio_percentage' => $share,
                'avg_interest_rate' => round((float) $g->avg_interest_rate, 2),
            ];
        }

        usort($result, fn($a, $b) => $b['total_principal'] <=> $a['total_principal']);
        return $result;
    }

    /**
     * 12-Month forward projection of maturing deposits and expected proceeds.
     */
    public function get12MonthForecast(?int $institutionId = null): array
    {
        $query = Fdr::whereIn('lifecycle_status', ['active', 'submitted', 'verified', 'approved', 'maturing']);
        if ($institutionId) {
            $query->where('institution_id', $institutionId);
        }

        $fdrs = $query->get();
        $start = Carbon::today()->startOfMonth();
        $forecast = [];

        for ($i = 0; $i < 12; $i++) {
            $monthStart = $start->copy()->addMonths($i);
            $monthEnd = $monthStart->copy()->endOfMonth();
            $label = $monthStart->format('M Y');

            $maturingFdrs = $fdrs->filter(function ($fdr) use ($monthStart, $monthEnd) {
                $mat = Carbon::parse($fdr->maturity_date);
                return $mat->between($monthStart, $monthEnd);
            });

            $forecast[] = [
                'month_key' => $monthStart->format('Y-m'),
                'label' => $label,
                'maturing_count' => $maturingFdrs->count(),
                'maturing_principal' => round((float) $maturingFdrs->sum('principal_amount'), 2),
                'expected_interest' => round((float) $maturingFdrs->sum('expected_interest_amount'), 2),
                'expected_total_proceeds' => round((float) $maturingFdrs->sum('expected_maturity_amount'), 2),
            ];
        }

        return $forecast;
    }
}

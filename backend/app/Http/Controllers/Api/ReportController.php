<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fdr;
use App\Models\Institution;
use App\Models\VerificationCase;
use App\Models\Discrepancy;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function summary(Request $request)
    {
        $user = $request->user();

        $fdrQuery = Fdr::query();
        $instQuery = Institution::query();
        $caseQuery = VerificationCase::query();

        if ($user->isInstitutionUser()) {
            $fdrQuery->where('institution_id', $user->institution_id);
            $caseQuery->where('institution_id', $user->institution_id);
            $instCount = 1;
        } else {
            $instCount = $instQuery->count();
        }

        $totalFdrs = (clone $fdrQuery)->count();
        $totalAmount = (clone $fdrQuery)->where('lifecycle_status', 'approved')->sum('principal_amount');
        $approvedCount = (clone $fdrQuery)->where('lifecycle_status', 'approved')->count();
        $pendingCount = (clone $fdrQuery)->whereIn('lifecycle_status', ['submitted', 'under_review'])->count();
        $rejectedCount = (clone $fdrQuery)->where('lifecycle_status', 'rejected')->count();
        $maturingCount = (clone $fdrQuery)->whereBetween('maturity_date', [now(), now()->addDays(90)])->count();

        $openDiscrepancies = Discrepancy::where('resolution_status', 'open')->count();
        $highRiskCases = (clone $fdrQuery)->whereIn('risk_level', ['high', 'critical'])->count();

        // Lifecycle breakdown
        $statusCounts = (clone $fdrQuery)->selectRaw('lifecycle_status, count(*) as count')
            ->groupBy('lifecycle_status')
            ->pluck('count', 'lifecycle_status');

        return response()->json([
            'metrics' => [
                'total_institutions' => $instCount,
                'total_fdrs' => $totalFdrs,
                'total_amount_secured_inr' => (float) $totalAmount,
                'approved_count' => $approvedCount,
                'pending_verification' => $pendingCount,
                'rejected_count' => $rejectedCount,
                'maturing_90_days' => $maturingCount,
                'open_discrepancies' => $openDiscrepancies,
                'high_risk_cases' => $highRiskCases,
            ],
            'lifecycle_distribution' => $statusCounts,
        ]);
    }

    public function exportCsv(Request $request)
    {
        $user = $request->user();
        if (!$user->isAicteAdmin() && !$user->isAuditor() && !$user->isInstitutionUser()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = Fdr::with('institution');
        if ($user->isInstitutionUser()) {
            $query->where('institution_id', $user->institution_id);
        }

        $fdrs = $query->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="AICTE_FDR_Compliance_Report_' . date('Ymd_His') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($fdrs) {
            $file = fopen('php://output', 'w');
            // Write UTF-8 BOM
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // CSV Header
            fputcsv($file, [
                'Tracking No',
                'Institution Name',
                'AICTE ID',
                'Bank Name',
                'Branch',
                'IFSC',
                'FDR Number',
                'Principal Amount (INR)',
                'Interest Rate (%)',
                'Issue Date',
                'Maturity Date',
                'Holder Name',
                'Beneficiary Name',
                'Lifecycle Status',
                'Verification Status',
                'Risk Level',
            ]);

            foreach ($fdrs as $f) {
                fputcsv($file, [
                    $this->sanitizeCsvCell($f->fdr_tracking_no),
                    $this->sanitizeCsvCell($f->institution->name ?? 'N/A'),
                    $this->sanitizeCsvCell($f->institution->aicte_id ?? 'N/A'),
                    $this->sanitizeCsvCell($f->bank_name),
                    $this->sanitizeCsvCell($f->branch_name),
                    $this->sanitizeCsvCell($f->ifsc_code),
                    $this->sanitizeCsvCell($f->fdr_number),
                    number_format((float) $f->principal_amount, 2, '.', ''),
                    $f->interest_rate ?? '0.00',
                    $f->issue_date->toDateString(),
                    $f->maturity_date->toDateString(),
                    $this->sanitizeCsvCell($f->holder_name),
                    $this->sanitizeCsvCell($f->beneficiary_name),
                    $f->lifecycle_status,
                    $f->verification_status,
                    $f->risk_level,
                ]);
            }

            fclose($file);
        };

        $this->auditService->log('EXPORT_FDR_REPORT_CSV', 'Fdr', null, [
            'records_count' => $fdrs->count(),
        ]);

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Prevents CSV Formula Injection attacks by prefixing formula characters (=, +, -, @) with a single quote.
     */
    protected function sanitizeCsvCell(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $firstChar = substr($value, 0, 1);
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }
}

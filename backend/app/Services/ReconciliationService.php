<?php

namespace App\Services;

use App\Models\BankStatementEntry;
use App\Models\Fdr;
use App\Models\ReconciliationDiscrepancy;
use App\Models\ReconciliationRun;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ReconciliationService
{
    /**
     * Import statement lines and reconcile against active FDR records.
     */
    public function importAndReconcile(string $bankName, array $entries, User $user): ReconciliationRun
    {
        $runRef = 'REC-' . strtoupper(Str::random(6)) . '-' . date('Ymd');

        $run = ReconciliationRun::create([
            'run_reference' => $runRef,
            'bank_name' => $bankName,
            'statement_period_start' => Carbon::now()->subMonths(6)->toDateString(),
            'statement_period_end' => Carbon::now()->toDateString(),
            'total_statement_records' => count($entries),
            'matched_count' => 0,
            'discrepancy_count' => 0,
            'total_statement_amount' => 0.00,
            'total_matched_amount' => 0.00,
            'status' => 'completed',
            'performed_by' => $user->id,
        ]);

        $totalStmtAmount = 0.0;
        $totalMatchedAmount = 0.0;
        $matchedCount = 0;
        $discrepancyCount = 0;
        $matchedFdrIds = [];

        foreach ($entries as $raw) {
            $fdrNumber = trim($raw['fdr_number'] ?? ($raw['fdr_reference'] ?? ''));
            $accountNumber = trim($raw['account_number'] ?? ($raw['account_no'] ?? ''));
            $stmtAmount = (float) ($raw['principal_amount'] ?? ($raw['amount'] ?? 0));
            $txnDate = !empty($raw['transaction_date']) ? Carbon::parse($raw['transaction_date'])->toDateString() : Carbon::today()->toDateString();
            $description = $raw['description'] ?? "Statement entry for {$fdrNumber}";

            $totalStmtAmount += $stmtAmount;

            // Find matching FDR in portal
            $fdr = Fdr::where('fdr_number', $fdrNumber)
                ->orWhere(function ($q) use ($accountNumber) {
                    if (!empty($accountNumber)) {
                        $q->where('account_number_ref', $accountNumber);
                    }
                })->first();

            $matchStatus = 'unmatched';
            $matchedFdrId = $fdr ? $fdr->id : null;

            $stmtEntry = BankStatementEntry::create([
                'reconciliation_run_id' => $run->id,
                'bank_name' => $bankName,
                'account_no' => $accountNumber,
                'fdr_reference' => $fdrNumber,
                'transaction_date' => $txnDate,
                'value_date' => $txnDate,
                'entry_type' => 'fdr_deposit',
                'amount' => $stmtAmount,
                'balance_after' => $stmtAmount,
                'description' => $description,
                'matched_fdr_id' => $matchedFdrId,
                'match_status' => 'pending',
            ]);

            if ($fdr) {
                $matchedFdrIds[] = $fdr->id;
                $portalPrincipal = (float) $fdr->principal_amount;
                $diff = abs($portalPrincipal - $stmtAmount);

                if ($diff <= 1.00) {
                    $matchStatus = 'matched';
                    $matchedCount++;
                    $totalMatchedAmount += $stmtAmount;
                } else {
                    $matchStatus = 'discrepant';
                    $discrepancyCount++;

                    ReconciliationDiscrepancy::create([
                        'reconciliation_run_id' => $run->id,
                        'fdr_id' => $fdr->id,
                        'bank_statement_entry_id' => $stmtEntry->id,
                        'discrepancy_type' => 'AMOUNT_MISMATCH',
                        'recorded_amount' => $portalPrincipal,
                        'bank_evidenced_amount' => $stmtAmount,
                        'difference_amount' => round($stmtAmount - $portalPrincipal, 2),
                        'status' => 'open',
                        'investigation_notes' => "Bank statement evidenced amount (INR {$stmtAmount}) differs from portal recorded principal (INR {$portalPrincipal}).",
                    ]);
                }
            } else {
                $discrepancyCount++;
                $matchStatus = 'unmatched';

                ReconciliationDiscrepancy::create([
                    'reconciliation_run_id' => $run->id,
                    'fdr_id' => null,
                    'bank_statement_entry_id' => $stmtEntry->id,
                    'discrepancy_type' => 'UNMATCHED_STATEMENT_ENTRY',
                    'recorded_amount' => 0.00,
                    'bank_evidenced_amount' => $stmtAmount,
                    'difference_amount' => $stmtAmount,
                    'status' => 'open',
                    'investigation_notes' => "Deposit of INR {$stmtAmount} under certificate {$fdrNumber} found on bank statement but missing in AICTE portal.",
                ]);
            }

            $stmtEntry->update(['match_status' => $matchStatus]);
        }

        $run->update([
            'matched_count' => $matchedCount,
            'discrepancy_count' => $discrepancyCount,
            'total_statement_amount' => round($totalStmtAmount, 2),
            'total_matched_amount' => round($totalMatchedAmount, 2),
            'status' => $discrepancyCount > 0 ? 'needs_review' : 'completed',
        ]);

        return $run->fresh(['statementEntries', 'discrepancies.fdr']);
    }

    /**
     * Resolve a reconciliation discrepancy.
     */
    public function resolveDiscrepancy(int $discrepancyId, string $resolutionNotes, User $user): ReconciliationDiscrepancy
    {
        $discrepancy = ReconciliationDiscrepancy::findOrFail($discrepancyId);
        $discrepancy->update([
            'status' => 'resolved',
            'investigation_notes' => $discrepancy->investigation_notes . "\n[Resolution Notes by {$user->name}]: " . $resolutionNotes,
            'resolved_by' => $user->id,
            'resolved_at' => Carbon::now(),
        ]);

        return $discrepancy;
    }
}

<?php

namespace App\Services;

use App\Models\Fdr;
use App\Models\Document;
use App\Models\OcrExtraction;
use App\Models\ValidationRun;
use App\Models\Discrepancy;
use Carbon\Carbon;

class DiscrepancyDetectionEngine
{
    /**
     * Executes all automated validation checks for an FDR and its uploaded documents.
     */
    public function validateFdr(Fdr $fdr, ?int $runnerId = null): ValidationRun
    {
        $discrepancies = [];
        $rulesChecked = 0;

        // Fetch latest completed OCR extraction
        $ocr = OcrExtraction::where('fdr_id', $fdr->id)
            ->where('status', 'completed')
            ->latest()
            ->first();

        // 1. Rule: Amount Mismatch
        $rulesChecked++;
        if ($ocr && !empty($ocr->extracted_data['principal_amount'])) {
            $ocrAmount = (float) $ocr->extracted_data['principal_amount'];
            $submittedAmount = (float) $fdr->principal_amount;

            if (abs($ocrAmount - $submittedAmount) > 0.01) {
                $discrepancies[] = [
                    'rule_identifier' => 'AMOUNT_MISMATCH',
                    'category' => 'financial',
                    'severity' => 'high_priority',
                    'field_name' => 'principal_amount',
                    'submitted_value' => 'INR ' . number_format($submittedAmount, 2),
                    'observed_value' => 'INR ' . number_format($ocrAmount, 2),
                    'explanation' => "The FDR principal amount extracted via OCR differs from the amount submitted by the institute.",
                ];
            }
        }

        // 2. Rule: FDR Number Mismatch
        $rulesChecked++;
        if ($ocr && !empty($ocr->extracted_data['fdr_number'])) {
            $ocrFdrNo = trim($ocr->extracted_data['fdr_number']);
            $submittedFdrNo = trim($fdr->fdr_number);

            if (strcasecmp($ocrFdrNo, $submittedFdrNo) !== 0) {
                $discrepancies[] = [
                    'rule_identifier' => 'FDR_NUMBER_MISMATCH',
                    'category' => 'identity',
                    'severity' => 'requires_review',
                    'field_name' => 'fdr_number',
                    'submitted_value' => $submittedFdrNo,
                    'observed_value' => $ocrFdrNo,
                    'explanation' => "The FDR certificate number on the physical document does not match the submitted record.",
                ];
            }
        }

        // 3. Rule: Bank Name Mismatch
        $rulesChecked++;
        if ($ocr && !empty($ocr->extracted_data['bank_name'])) {
            $ocrBank = trim($ocr->extracted_data['bank_name']);
            $submittedBank = trim($fdr->bank_name);

            if (stripos($submittedBank, $ocrBank) === false && stripos($ocrBank, $submittedBank) === false) {
                $discrepancies[] = [
                    'rule_identifier' => 'BANK_NAME_MISMATCH',
                    'category' => 'identity',
                    'severity' => 'warning',
                    'field_name' => 'bank_name',
                    'submitted_value' => $submittedBank,
                    'observed_value' => $ocrBank,
                    'explanation' => "Issuing bank identified in OCR differs from the selected bank name.",
                ];
            }
        }

        // 4. Rule: Date Inconsistency
        $rulesChecked++;
        $issueDate = Carbon::parse($fdr->issue_date);
        $maturityDate = Carbon::parse($fdr->maturity_date);

        if ($maturityDate->lte($issueDate)) {
            $discrepancies[] = [
                'rule_identifier' => 'DATE_INCONSISTENCY',
                'category' => 'lifecycle',
                'severity' => 'verification_blocked',
                'field_name' => 'maturity_date',
                'submitted_value' => $fdr->maturity_date->toDateString(),
                'observed_value' => "Issue Date: " . $fdr->issue_date->toDateString(),
                'explanation' => "The maturity date is earlier than or equal to the issue date.",
            ];
        }

        // 5. Rule: Expired or Past Maturity
        $rulesChecked++;
        if ($maturityDate->isPast()) {
            $discrepancies[] = [
                'rule_identifier' => 'EXPIRED_FDR',
                'category' => 'lifecycle',
                'severity' => 'verification_blocked',
                'field_name' => 'maturity_date',
                'submitted_value' => $fdr->maturity_date->toDateString(),
                'observed_value' => 'Today: ' . now()->toDateString(),
                'explanation' => "This Fixed Deposit has already matured and expired. Renewal or updated receipt required.",
            ];
        } elseif ($maturityDate->diffInDays(now()) <= 90) {
            $rulesChecked++;
            $discrepancies[] = [
                'rule_identifier' => 'APPROACHING_MATURITY',
                'category' => 'lifecycle',
                'severity' => 'warning',
                'field_name' => 'maturity_date',
                'submitted_value' => $fdr->maturity_date->toDateString(),
                'observed_value' => $maturityDate->diffInDays(now()) . " days remaining",
                'explanation' => "This FDR matures within 90 days. Institution must initiate renewal proceedings.",
            ];
        }

        // 6. Rule: Cross-Tenant Duplicate FDR Number (Fraud Detection)
        $rulesChecked++;
        $duplicateFdr = Fdr::where('fdr_number', $fdr->fdr_number)
            ->where('id', '!=', $fdr->id)
            ->where('institution_id', '!=', $fdr->institution_id)
            ->first();

        if ($duplicateFdr) {
            $discrepancies[] = [
                'rule_identifier' => 'CROSS_TENANT_DUPLICATE_FDR',
                'category' => 'tampering',
                'severity' => 'verification_blocked',
                'field_name' => 'fdr_number',
                'submitted_value' => $fdr->fdr_number,
                'observed_value' => "Duplicate in Institution ID #{$duplicateFdr->institution_id}",
                'explanation' => "SUSPICIOUS PATTERN: This identical FDR number has also been registered by another educational institution.",
            ];
        }

        // 7. Rule: Duplicate Document Hash Detection
        $rulesChecked++;
        $doc = Document::where('fdr_id', $fdr->id)->orderBy('id', 'desc')->first();
        if ($doc) {
            $duplicateDoc = Document::where('file_hash_sha256', $doc->file_hash_sha256)
                ->where('id', '!=', $doc->id)
                ->where('institution_id', '!=', $fdr->institution_id)
                ->first();

            if ($duplicateDoc) {
                $discrepancies[] = [
                    'rule_identifier' => 'DUPLICATE_DOCUMENT_HASH',
                    'category' => 'tampering',
                    'severity' => 'verification_blocked',
                    'field_name' => 'document_hash',
                    'submitted_value' => substr($doc->file_hash_sha256, 0, 16) . '...',
                    'observed_value' => "Found in Doc #{$duplicateDoc->id} (Inst #{$duplicateDoc->institution_id})",
                    'explanation' => "INTEGRITY ALERT: Exact binary match detected for a document uploaded under a different institution.",
                ];
            }
        }

        // 8. Rule: Direct Bank Document SHA-256 Digest Mismatch
        $rulesChecked++;
        if ($doc && $doc->bank_sha_match_status === 'mismatch') {
            $discrepancies[] = [
                'rule_identifier' => 'BANK_SHA256_MISMATCH',
                'category' => 'tampering',
                'severity' => 'verification_blocked',
                'field_name' => 'document_sha256',
                'submitted_value' => substr((string) $doc->file_hash_sha256, 0, 16) . '...',
                'observed_value' => substr((string) $doc->bank_sha256, 0, 16) . '...',
                'explanation' => "CRITICAL TAMPER ALERT: The uploaded document's SHA-256 hash does not match the bank's official electronic receipt digest! The PDF or certificate has been altered or modified.",
            ];
        }
        $hasBlocked = collect($discrepancies)->contains('severity', 'verification_blocked');
        $hasHigh = collect($discrepancies)->contains('severity', 'high_priority');
        $hasWarnings = count($discrepancies) > 0;

        $runStatus = $hasBlocked ? 'blocked' : ($hasWarnings ? 'flagged_warnings' : 'passed');
        $riskLevel = $hasBlocked ? 'critical' : ($hasHigh ? 'high' : ($hasWarnings ? 'medium' : 'low'));

        // Update FDR risk level
        $fdr->update(['risk_level' => $riskLevel]);

        // Create Validation Run
        $validationRun = ValidationRun::create([
            'fdr_id' => $fdr->id,
            'run_by' => $runnerId,
            'status' => $runStatus,
            'rules_checked_count' => $rulesChecked,
            'discrepancies_count' => count($discrepancies),
            'summary' => count($discrepancies) === 0
                ? "All {$rulesChecked} automated compliance rules passed with zero discrepancies."
                : "Detected " . count($discrepancies) . " discrepancies across {$rulesChecked} automated compliance rules.",
        ]);

        // Create Discrepancy items
        foreach ($discrepancies as $d) {
            Discrepancy::create([
                'validation_run_id' => $validationRun->id,
                'fdr_id' => $fdr->id,
                'rule_identifier' => $d['rule_identifier'],
                'category' => $d['category'],
                'severity' => $d['severity'],
                'field_name' => $d['field_name'],
                'submitted_value' => $d['submitted_value'],
                'observed_value' => $d['observed_value'],
                'explanation' => $d['explanation'],
                'resolution_status' => 'open',
            ]);
        }

        return $validationRun;
    }
}

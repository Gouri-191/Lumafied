<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OcrExtraction;
use App\Models\Fdr;
use App\Services\AuditService;
use App\Services\DiscrepancyDetectionEngine;
use App\Services\OcrExtractionService;
use Illuminate\Http\Request;

class OcrController extends Controller
{
    protected AuditService $auditService;
    protected DiscrepancyDetectionEngine $discrepancyEngine;

    public function __construct(AuditService $auditService, DiscrepancyDetectionEngine $discrepancyEngine)
    {
        $this->auditService = $auditService;
        $this->discrepancyEngine = $discrepancyEngine;
    }

    /**
     * Pre-submission OCR & Document metadata scan to auto-fill FDR fields.
     */
    public function scanPreview(Request $request, OcrExtractionService $ocrService)
    {
        $request->validate([
            'file' => 'required|file|max:15360',
        ]);

        $file = $request->file('file');
        $result = $ocrService->extractFieldsFromUploadedFile($file);

        return response()->json($result);
    }

    public function getResults(Request $request, $fdrId)
    {
        $user = $request->user();
        $fdr = Fdr::findOrFail($fdrId);

        if (!$user->canAccessInstitution($fdr->institution_id)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $ocr = OcrExtraction::with(['document', 'corrector'])
            ->where('fdr_id', $fdr->id)
            ->latest()
            ->first();

        if (!$ocr) {
            return response()->json(['message' => 'No OCR extraction records found for this FDR.'], 404);
        }

        return response()->json($ocr);
    }

    public function submitCorrections(Request $request, $fdrId, $ocrId)
    {
        $user = $request->user();
        $fdr = Fdr::findOrFail($fdrId);

        if (!$user->canAccessInstitution($fdr->institution_id)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $ocr = OcrExtraction::where('fdr_id', $fdr->id)->findOrFail($ocrId);

        $validated = $request->validate([
            'corrected_data' => 'required|array',
            'corrected_data.bank_name' => 'nullable|string',
            'corrected_data.branch_name' => 'nullable|string',
            'corrected_data.fdr_number' => 'nullable|string',
            'corrected_data.principal_amount' => 'nullable|numeric',
            'corrected_data.issue_date' => 'nullable|date',
            'corrected_data.maturity_date' => 'nullable|date',
            'correction_reason' => 'required|string|min:5',
        ]);

        $ocr->update([
            'corrected_data' => $validated['corrected_data'],
            'corrected_by' => $user->id,
            'corrected_at' => now(),
        ]);

        // Re-run discrepancy validation with updated extracted info
        $this->discrepancyEngine->validateFdr($fdr, $user->id);

        $this->auditService->log('SUBMIT_OCR_CORRECTION', 'OcrExtraction', (string) $ocr->id, [
            'fdr_id' => $fdr->id,
            'corrections' => $validated['corrected_data'],
            'reason' => $validated['correction_reason'],
        ]);

        return response()->json([
            'message' => 'OCR corrections submitted and validation rules re-evaluated.',
            'ocr' => $ocr->fresh('corrector'),
        ]);
    }
}

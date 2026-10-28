<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fdr;
use App\Models\BankVerificationRequest;
use App\Services\BankVerificationService;
use App\Services\AuditService;
use Illuminate\Http\Request;

class BankVerificationController extends Controller
{
    protected BankVerificationService $bankService;
    protected AuditService $auditService;

    public function __construct(BankVerificationService $bankService, AuditService $auditService)
    {
        $this->bankService = $bankService;
        $this->auditService = $auditService;
    }

    public function initiate(Request $request, $fdrId)
    {
        $user = $request->user();
        if (!$user->isVerificationOfficer() && !$user->isAicteAdmin()) {
            return response()->json(['message' => 'Unauthorized: Only verification officers can trigger bank verification.'], 403);
        }

        $fdr = Fdr::findOrFail($fdrId);

        if ($fdr->documents()->count() === 0) {
            return response()->json([
                'message' => 'Cannot initiate bank verification: No physical FDR receipt document has been uploaded for this record.',
            ], 422);
        }

        $request->validate([
            'simulate_outcome' => 'nullable|in:confirmed,mismatch,not_found',
        ]);

        $outcome = $request->input('simulate_outcome', 'confirmed');
        $verificationReq = $this->bankService->initiateVerification($fdr, $user, $outcome);

        $this->auditService->log('TRIGGER_BANK_VERIFICATION', 'BankVerificationRequest', (string) $verificationReq->id, [
            'fdr_id' => $fdr->id,
            'bank_name' => $fdr->bank_name,
            'outcome' => $outcome,
            'simulated' => true,
        ]);

        return response()->json([
            'message' => 'Bank verification inquiry completed (Simulated Adapter).',
            'bank_verification' => $verificationReq,
            'fdr' => $fdr->fresh(['bankVerificationRequests']),
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $req = BankVerificationRequest::with('fdr.institution')->findOrFail($id);

        if (!$user->canAccessInstitution($req->fdr->institution_id)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($req);
    }
}

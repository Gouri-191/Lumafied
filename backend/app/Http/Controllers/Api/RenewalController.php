<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fdr;
use App\Models\FdrRenewal;
use App\Services\RenewalPlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RenewalController extends Controller
{
    protected RenewalPlanningService $renewalService;

    public function __construct(RenewalPlanningService $renewalService)
    {
        $this->renewalService = $renewalService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = FdrRenewal::with(['originalFdr.institution', 'newFdr', 'requester', 'approver'])
            ->orderBy('created_at', 'desc');

        if ($user->isInstitutionUser()) {
            $query->where('institution_id', $user->institution_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate(20),
        ]);
    }

    public function scenarios(int $fdrId): JsonResponse
    {
        $fdr = Fdr::with('institution')->findOrFail($fdrId);
        $scenarios = $this->renewalService->calculateScenarioOptions($fdr);

        return response()->json([
            'success' => true,
            'data' => $scenarios,
        ]);
    }

    public function requestRenewal(Request $request, int $fdrId): JsonResponse
    {
        $fdr = Fdr::findOrFail($fdrId);

        $validated = $request->validate([
            'renewal_option' => 'required|string|in:option_a,option_b,option_c',
            'proposed_principal' => 'required|numeric|min:1',
            'proposed_rate' => 'required|numeric|min:0.1|max:25',
            'proposed_tenure_months' => 'required|integer|min:1|max:120',
            'estimated_maturity_amount' => 'required|numeric|min:1',
        ]);

        $renewal = $this->renewalService->requestRenewal($fdr, $validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'FDR renewal request submitted for regulatory approval.',
            'data' => $renewal,
        ], 201);
    }

    public function approveRenewal(Request $request, int $renewalId): JsonResponse
    {
        $user = $request->user();
        if (!$user->isAicteAdmin() && !$user->isVerificationOfficer()) {
            return response()->json(['message' => 'Unauthorized. Only AICTE officials can authorize renewals.'], 403);
        }

        $renewal = FdrRenewal::with('originalFdr')->findOrFail($renewalId);
        if ($renewal->status !== 'pending_review') {
            return response()->json(['message' => 'Renewal request is not pending review.'], 422);
        }

        $notes = $request->input('decision_notes', 'Approved pursuant to AICTE renewal policy.');
        $newFdr = $this->renewalService->approveRenewal($renewal, $user, $notes);

        return response()->json([
            'success' => true,
            'message' => 'Renewal approved. Linked FDR version spawned successfully.',
            'data' => [
                'renewal' => $renewal->fresh(['newFdr']),
                'new_fdr' => $newFdr,
            ],
        ]);
    }
}

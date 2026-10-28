<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VerificationCase;
use App\Models\User;
use App\Services\AuditService;
use App\Services\FdrLifecycleService;
use Illuminate\Http\Request;

class VerificationCaseController extends Controller
{
    protected AuditService $auditService;
    protected FdrLifecycleService $lifecycleService;

    public function __construct(AuditService $auditService, FdrLifecycleService $lifecycleService)
    {
        $this->auditService = $auditService;
        $this->lifecycleService = $lifecycleService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = VerificationCase::with(['fdr.institution', 'assignedOfficer']);

        if ($user->isVerificationOfficer()) {
            $query->where('assigned_officer_id', $user->id);
        } elseif ($user->isInstitutionUser()) {
            $query->where('institution_id', $user->institution_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        return response()->json($query->latest()->paginate($request->input('per_page', 15)));
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $case = VerificationCase::with([
            'fdr.institution',
            'fdr.documents',
            'fdr.discrepancies.resolver',
            'fdr.bankVerificationRequests',
            'fdr.statusHistory.actor',
            'assignedOfficer',
            'assigner',
        ])->findOrFail($id);

        if (!$user->canAccessInstitution($case->institution_id)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($case);
    }

    public function assign(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isAicteAdmin()) {
            return response()->json(['message' => 'Only AICTE administrators can assign verification officers.'], 403);
        }

        $case = VerificationCase::with('fdr')->findOrFail($id);

        $request->validate([
            'officer_id' => 'required|exists:users,id',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'due_date' => 'nullable|date',
        ]);

        $officer = User::findOrFail($request->officer_id);
        if (!$officer->isVerificationOfficer() && !$officer->isAicteAdmin()) {
            return response()->json(['message' => 'Selected user is not designated as a verification officer.'], 422);
        }

        if ($request->filled('priority')) {
            $case->priority = $request->priority;
        }
        if ($request->filled('due_date')) {
            $case->due_date = $request->due_date;
        }
        $case->save();

        $updated = $this->lifecycleService->assignOfficer($case->fdr, $officer, $user);

        return response()->json([
            'message' => 'Verification case assigned successfully.',
            'case' => $updated->fresh(['fdr', 'assignedOfficer']),
        ]);
    }

    public function clarify(Request $request, $id)
    {
        $user = $request->user();
        $case = VerificationCase::with('fdr')->findOrFail($id);

        if ($user->isInstitutionUser()) {
            // Submitter responding to clarification
            $request->validate(['clarification_response' => 'required|string|min:5']);
            $case->update([
                'clarification_response' => $request->clarification_response,
                'status' => 'under_review',
            ]);
            $case->fdr->update(['lifecycle_status' => 'resubmitted']);

            $this->auditService->log('RESPOND_CLARIFICATION', 'VerificationCase', (string) $case->id, [
                'response' => $request->clarification_response,
            ]);

            return response()->json(['message' => 'Clarification response submitted.', 'case' => $case]);
        }

        // Officer requesting clarification
        if (!$user->isVerificationOfficer() && !$user->isAicteAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate(['clarification_text' => 'required|string|min:5']);
        $case->update([
            'clarification_text' => $request->clarification_text,
            'status' => 'clarification_pending',
        ]);
        $this->lifecycleService->submitRecommendation($case->fdr, $user, 'request_clarification', $request->clarification_text);

        return response()->json(['message' => 'Clarification requested from institution.', 'case' => $case]);
    }

    public function recommend(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->isVerificationOfficer() && !$user->isAicteAdmin()) {
            return response()->json(['message' => 'Only assigned verification officers can submit recommendations.'], 403);
        }

        $case = VerificationCase::with('fdr')->findOrFail($id);

        $request->validate([
            'recommendation' => 'required|in:approve,reject,request_clarification',
            'notes' => 'required|string|min:5',
        ]);

        $updated = $this->lifecycleService->submitRecommendation(
            $case->fdr,
            $user,
            $request->recommendation,
            $request->notes
        );

        return response()->json([
            'message' => 'Verification recommendation recorded.',
            'case' => $updated->fresh(['fdr', 'assignedOfficer']),
        ]);
    }

    public function approve(Request $request, $id)
    {
        $user = $request->user();
        $case = VerificationCase::with('fdr')->findOrFail($id);

        $request->validate(['comments' => 'nullable|string']);

        try {
            $fdr = $this->lifecycleService->renderDecision($case->fdr, $user, 'approved', $request->comments);
            return response()->json([
                'message' => 'FDR successfully approved for AICTE regulatory compliance.',
                'fdr' => $fdr,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function reject(Request $request, $id)
    {
        $user = $request->user();
        $case = VerificationCase::with('fdr')->findOrFail($id);

        $request->validate(['comments' => 'required|string|min:5']);

        try {
            $fdr = $this->lifecycleService->renderDecision($case->fdr, $user, 'rejected', $request->comments);
            return response()->json([
                'message' => 'FDR rejected.',
                'fdr' => $fdr,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}

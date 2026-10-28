<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fdr;
use App\Services\AuditService;
use App\Services\FdrLifecycleService;
use App\Services\DiscrepancyDetectionEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FdrController extends Controller
{
    protected AuditService $auditService;
    protected FdrLifecycleService $lifecycleService;
    protected DiscrepancyDetectionEngine $discrepancyEngine;

    public function __construct(
        AuditService $auditService,
        FdrLifecycleService $lifecycleService,
        DiscrepancyDetectionEngine $discrepancyEngine
    ) {
        $this->auditService = $auditService;
        $this->lifecycleService = $lifecycleService;
        $this->discrepancyEngine = $discrepancyEngine;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Fdr::with(['institution', 'verificationCase']);

        // Strict tenant isolation
        if ($user->isInstitutionUser()) {
            $query->where('institution_id', $user->institution_id);
        } elseif ($request->filled('institution_id')) {
            $query->where('institution_id', $request->institution_id);
        }

        // Filtering
        if ($request->filled('lifecycle_status')) {
            $query->where('lifecycle_status', $request->lifecycle_status);
        }

        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }

        if ($request->filled('bank_name')) {
            $query->where('bank_name', 'like', "%{$request->bank_name}%");
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('fdr_tracking_no', 'like', "%{$s}%")
                  ->orWhere('fdr_number', 'like', "%{$s}%")
                  ->orWhere('holder_name', 'like', "%{$s}%")
                  ->orWhere('bank_name', 'like', "%{$s}%");
            });
        }

        // Maturity filters
        if ($request->boolean('maturing_soon')) {
            $query->whereBetween('maturity_date', [now()->toDateString(), now()->addDays(90)->toDateString()]);
        }

        return response()->json($query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15)));
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Determine institution ID based on actor role
        $institutionId = $user->isInstitutionUser()
            ? $user->institution_id
            : $request->input('institution_id');

        if (!$institutionId) {
            return response()->json(['message' => 'Institution ID is required.'], 422);
        }

        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:150', new \App\Rules\SafeText],
            'branch_name' => ['required', 'string', 'max:150', new \App\Rules\SafeText],
            'ifsc_code' => ['required', 'string', 'max:30', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/i'],
            'fdr_number' => ['required', 'string', 'max:100', new \App\Rules\SafeText],
            'account_number_ref' => ['nullable', 'string', 'max:100', new \App\Rules\SafeText],
            'principal_amount' => 'required|numeric|min:1',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'issue_date' => 'required|date',
            'maturity_date' => 'required|date|after:issue_date',
            'tenure_months' => 'required|integer|min:1',
            'holder_name' => ['required', 'string', 'max:150', new \App\Rules\SafeText],
            'beneficiary_name' => ['required', 'string', 'max:150', new \App\Rules\SafeText],
            'purpose' => ['nullable', 'string', 'max:200', new \App\Rules\SafeText],
        ]);

        $trackingNo = 'FDR-' . date('Y') . '-' . strtoupper(Str::random(8));

        $fdr = Fdr::create(array_merge($validated, [
            'fdr_tracking_no' => $trackingNo,
            'institution_id' => $institutionId,
            'currency' => 'INR',
            'lifecycle_status' => 'draft',
            'verification_status' => 'pending',
            'created_by' => $user->id,
            'last_updated_by' => $user->id,
        ]));

        $this->auditService->log('CREATE_FDR_DRAFT', 'Fdr', (string) $fdr->id, [
            'tracking_no' => $trackingNo,
            'amount' => $fdr->principal_amount,
        ]);

        return response()->json($fdr, 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $fdr = Fdr::with([
            'institution',
            'documents',
            'verificationCase.assignedOfficer',
            'validationRuns.discrepancies',
            'bankVerificationRequests',
            'statusHistory.actor',
            'releaseRequests',
        ])->findOrFail($id);

        if (!$user->canAccessInstitution($fdr->institution_id)) {
            return response()->json(['message' => 'Unauthorized access to FDR record.'], 403);
        }

        return response()->json($fdr);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $fdr = Fdr::findOrFail($id);

        if (!$user->canAccessInstitution($fdr->institution_id)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($user->isInstitutionUser() && !in_array($fdr->lifecycle_status, ['draft', 'clarification_requested'], true)) {
            return response()->json(['message' => 'FDR can only be edited while in draft or clarification-requested status.'], 422);
        }

        $validated = $request->validate([
            'bank_name' => ['sometimes', 'required', 'string', 'max:150', new \App\Rules\SafeText],
            'branch_name' => ['sometimes', 'required', 'string', 'max:150', new \App\Rules\SafeText],
            'ifsc_code' => ['sometimes', 'required', 'string', 'max:30'],
            'fdr_number' => ['sometimes', 'required', 'string', 'max:100', new \App\Rules\SafeText],
            'account_number_ref' => ['nullable', 'string', 'max:100', new \App\Rules\SafeText],
            'principal_amount' => 'sometimes|required|numeric|min:1',
            'interest_rate' => 'nullable|numeric',
            'issue_date' => 'sometimes|required|date',
            'maturity_date' => 'sometimes|required|date',
            'tenure_months' => 'sometimes|required|integer|min:1',
            'holder_name' => ['sometimes', 'required', 'string', 'max:150', new \App\Rules\SafeText],
            'beneficiary_name' => ['sometimes', 'required', 'string', 'max:150', new \App\Rules\SafeText],
            'purpose' => ['nullable', 'string', 'max:200', new \App\Rules\SafeText],
        ]);

        $fdr->update(array_merge($validated, ['last_updated_by' => $user->id]));
        $this->auditService->log('UPDATE_FDR', 'Fdr', (string) $fdr->id, $validated);

        return response()->json($fdr);
    }

    public function submit(Request $request, $id)
    {
        $user = $request->user();
        $fdr = Fdr::findOrFail($id);

        if (!$user->canAccessInstitution($fdr->institution_id)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Ensure at least one supporting original document exists
        if ($fdr->documents()->count() === 0) {
            return response()->json(['message' => 'Cannot submit FDR without uploading the original Fixed Deposit Receipt document.'], 422);
        }

        $submitted = $this->lifecycleService->submitFdr($fdr, $user);

        // Run automated compliance check
        $this->discrepancyEngine->validateFdr($submitted, $user->id);

        return response()->json([
            'message' => 'FDR successfully submitted for AICTE verification.',
            'fdr' => $submitted->fresh(['verificationCase', 'validationRuns.discrepancies']),
        ]);
    }

    public function validateRun(Request $request, $id)
    {
        $user = $request->user();
        $fdr = Fdr::findOrFail($id);

        if (!$user->canAccessInstitution($fdr->institution_id)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $run = $this->discrepancyEngine->validateFdr($fdr, $user->id);

        return response()->json([
            'message' => 'Validation executed successfully.',
            'validation_run' => $run->load('discrepancies'),
        ]);
    }

    public function history(Request $request, $id)
    {
        $user = $request->user();
        $fdr = Fdr::findOrFail($id);

        if (!$user->canAccessInstitution($fdr->institution_id)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($fdr->statusHistory()->with('actor')->get());
    }

    public function discrepancies(Request $request, $id)
    {
        $user = $request->user();
        $fdr = Fdr::findOrFail($id);

        if (!$user->canAccessInstitution($fdr->institution_id)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($fdr->discrepancies()->with('resolver')->latest()->get());
    }

    public function resolveDiscrepancy(Request $request, $id, $discrepancyId)
    {
        $user = $request->user();
        if (!$user->isVerificationOfficer() && !$user->isAicteAdmin()) {
            return response()->json(['message' => 'Only verification officers or administrators can resolve discrepancies.'], 403);
        }

        $fdr = Fdr::findOrFail($id);
        $discrepancy = $fdr->discrepancies()->findOrFail($discrepancyId);

        $validated = $request->validate([
            'resolution_status' => 'required|in:resolved,waived,escalated',
            'resolution_notes' => 'required|string|min:5',
        ]);

        $discrepancy->update([
            'resolution_status' => $validated['resolution_status'],
            'resolution_notes' => $validated['resolution_notes'],
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ]);

        $this->auditService->log('RESOLVE_DISCREPANCY', 'Discrepancy', (string) $discrepancy->id, $validated);

        return response()->json([
            'message' => 'Discrepancy resolution recorded.',
            'discrepancy' => $discrepancy->fresh('resolver'),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fdr;
use App\Models\ReleaseRequest;
use App\Models\FdrStatusHistory;
use App\Services\AuditService;
use Illuminate\Http\Request;

class LifecycleController extends Controller
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function maturityCalendar(Request $request)
    {
        $user = $request->user();
        $query = Fdr::with('institution');

        if ($user->isInstitutionUser()) {
            $query->where('institution_id', $user->institution_id);
        }

        // Return FDRs grouped or sorted by maturity
        $fdrs = $query->orderBy('maturity_date', 'asc')->get();

        $today = now();
        $stats = [
            'expired' => $fdrs->filter(fn ($f) => $f->maturity_date->isPast())->values(),
            'maturing_30_days' => $fdrs->filter(fn ($f) => !$f->maturity_date->isPast() && $f->maturity_date->diffInDays($today) <= 30)->values(),
            'maturing_90_days' => $fdrs->filter(fn ($f) => !$f->maturity_date->isPast() && $f->maturity_date->diffInDays($today) > 30 && $f->maturity_date->diffInDays($today) <= 90)->values(),
            'active_healthy' => $fdrs->filter(fn ($f) => !$f->maturity_date->isPast() && $f->maturity_date->diffInDays($today) > 90)->values(),
        ];

        return response()->json($stats);
    }

    public function requestRelease(Request $request, $fdrId)
    {
        $user = $request->user();
        $fdr = Fdr::findOrFail($fdrId);

        if (!$user->canAccessInstitution($fdr->institution_id)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (!in_array($fdr->lifecycle_status, ['approved', 'maturing', 'matured'], true)) {
            return response()->json(['message' => 'Release can only be requested for approved or matured FDRs.'], 422);
        }

        $request->validate([
            'reason' => 'required|string|min:10',
            'no_dues_certificate_ref' => 'nullable|string',
        ]);

        $release = ReleaseRequest::create([
            'fdr_id' => $fdr->id,
            'institution_id' => $fdr->institution_id,
            'reason' => $request->reason,
            'status' => 'requested',
            'requested_by' => $user->id,
            'no_dues_certificate_ref' => $request->no_dues_certificate_ref,
        ]);

        $fdr->update(['lifecycle_status' => 'release_requested']);

        FdrStatusHistory::create([
            'fdr_id' => $fdr->id,
            'from_status' => 'approved',
            'to_status' => 'release_requested',
            'actor_id' => $user->id,
            'action' => 'REQUEST_RELEASE',
            'comments' => "Release request submitted. Reason: {$request->reason}",
        ]);

        $this->auditService->log('REQUEST_RELEASE', 'ReleaseRequest', (string) $release->id, [
            'fdr_id' => $fdr->id,
            'reason' => $request->reason,
        ]);

        return response()->json([
            'message' => 'Release request lodged successfully.',
            'release_request' => $release,
        ], 201);
    }

    public function approveRelease(Request $request, $releaseId)
    {
        $user = $request->user();
        if (!$user->isApprovingAuthority() && !$user->isAicteAdmin()) {
            return response()->json(['message' => 'Unauthorized: Approving authority role required.'], 403);
        }

        $release = ReleaseRequest::with('fdr')->findOrFail($releaseId);
        $fdr = $release->fdr;

        $request->validate(['decision_notes' => 'required|string|min:5']);

        $release->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'decision_notes' => $request->decision_notes,
        ]);

        $fdr->update(['lifecycle_status' => 'released']);

        FdrStatusHistory::create([
            'fdr_id' => $fdr->id,
            'from_status' => 'release_requested',
            'to_status' => 'released',
            'actor_id' => $user->id,
            'action' => 'APPROVE_RELEASE',
            'comments' => "Lien discharged and FDR released by AICTE: {$request->decision_notes}",
        ]);

        $this->auditService->log('APPROVE_RELEASE', 'ReleaseRequest', (string) $release->id, [
            'fdr_id' => $fdr->id,
            'decision_notes' => $request->decision_notes,
        ]);

        return response()->json([
            'message' => 'FDR release approved and lien discharged.',
            'release_request' => $release,
            'fdr' => $fdr,
        ]);
    }
}

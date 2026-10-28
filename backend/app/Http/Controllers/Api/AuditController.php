<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Services\AuditService;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Enforce privilege: Only Auditors, AICTE Admins, and System Admins can inspect system audit logs
        if (!$user->isAuditor() && !$user->isAicteAdmin()) {
            return response()->json(['message' => 'Unauthorized: Only designated auditors and administrators may access audit trails.'], 403);
        }

        $query = AuditEvent::with(['user', 'institution']);

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->resource_type);
        }

        if ($request->filled('resource_id')) {
            $query->where('resource_id', $request->resource_id);
        }

        if ($request->filled('actor_email')) {
            $query->where('actor_email', 'like', "%{$request->actor_email}%");
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        $events = $query->orderBy('id', 'desc')->paginate(25);

        return response()->json($events);
    }

    public function verifyChain(AuditService $auditService, Request $request)
    {
        $user = $request->user();

        if (!$user->isAuditor() && !$user->isAicteAdmin()) {
            return response()->json(['message' => 'Unauthorized: Only designated auditors and administrators may verify the cryptographic audit chain.'], 403);
        }

        $result = $auditService->verifyAuditChain();
        return response()->json($result, $result['valid'] ? 200 : 409);
    }
}

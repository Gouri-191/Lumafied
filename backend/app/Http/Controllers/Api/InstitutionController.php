<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class InstitutionController extends Controller
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        // Enforce institution boundary
        $query = Institution::withCount('fdrs');

        if ($user->isInstitutionUser()) {
            $query->where('id', $user->institution_id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('aicte_id', 'like', "%{$s}%")
                  ->orWhere('city', 'like', "%{$s}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        return response()->json($query->paginate($request->input('per_page', 15)));
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->canAccessInstitution((int) $id)) {
            return response()->json(['message' => 'Unauthorized access to institution record.'], 403);
        }

        $institution = Institution::withCount('fdrs')->findOrFail($id);
        return response()->json($institution);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user->isAicteAdmin()) {
            return response()->json(['message' => 'Only AICTE administrators can register new institutions.'], 403);
        }

        $validated = $request->validate([
            'aicte_id' => 'required|string|unique:institutions,aicte_id',
            'name' => 'required|string|max:200',
            'category' => 'required|string|max:100',
            'address' => 'nullable|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'pincode' => 'required|string',
            'contact_email' => 'required|email',
            'contact_phone' => 'nullable|string',
        ]);

        $institution = Institution::create($validated);
        $this->auditService->log('CREATE_INSTITUTION', 'Institution', (string) $institution->id, $validated);

        return response()->json($institution, 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->canAccessInstitution((int) $id)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $institution = Institution::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:200',
            'address' => 'nullable|string',
            'city' => 'sometimes|required|string',
            'state' => 'sometimes|required|string',
            'pincode' => 'sometimes|required|string',
            'contact_email' => 'sometimes|required|email',
            'contact_phone' => 'nullable|string',
        ]);

        $institution->update($validated);
        $this->auditService->log('UPDATE_INSTITUTION', 'Institution', (string) $institution->id, $validated);

        return response()->json($institution);
    }

    public function users(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->canAccessInstitution((int) $id)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $users = User::where('institution_id', $id)->get();
        return response()->json($users);
    }
}

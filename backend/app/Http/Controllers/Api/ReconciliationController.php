<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReconciliationRun;
use App\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    protected ReconciliationService $reconciliationService;

    public function __construct(ReconciliationService $reconciliationService)
    {
        $this->reconciliationService = $reconciliationService;
    }

    public function index(): JsonResponse
    {
        $runs = ReconciliationRun::with('performer')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $runs,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $run = ReconciliationRun::with([
            'performer',
            'statementEntries.matchedFdr.institution',
            'discrepancies.fdr.institution',
            'discrepancies.resolver'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $run,
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'bank_name' => 'required|string|max:100',
            'entries' => 'required|array|min:1',
            'entries.*.fdr_number' => 'required|string',
            'entries.*.principal_amount' => 'required|numeric|min:0',
            'entries.*.interest_rate' => 'nullable|numeric',
            'entries.*.maturity_date' => 'nullable|date',
            'entries.*.holder_name' => 'nullable|string',
        ]);

        $run = $this->reconciliationService->importAndReconcile(
            $request->input('bank_name'),
            $request->input('entries'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Bank statement imported and reconciled successfully.',
            'data' => $run,
        ], 201);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'resolution_notes' => 'required|string|min:5|max:1000',
        ]);

        $resolved = $this->reconciliationService->resolveDiscrepancy(
            $id,
            $request->input('resolution_notes'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Reconciliation discrepancy resolved.',
            'data' => $resolved,
        ]);
    }
}

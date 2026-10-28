<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TreasuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreasuryController extends Controller
{
    protected TreasuryService $treasuryService;

    public function __construct(TreasuryService $treasuryService)
    {
        $this->treasuryService = $treasuryService;
    }

    public function metrics(Request $request): JsonResponse
    {
        $user = $request->user();
        $institutionId = $user->isInstitutionUser() ? $user->institution_id : $request->query('institution_id');

        $metrics = $this->treasuryService->getTreasuryMetrics($institutionId);

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    public function maturityLadder(Request $request): JsonResponse
    {
        $user = $request->user();
        $institutionId = $user->isInstitutionUser() ? $user->institution_id : $request->query('institution_id');

        $ladder = $this->treasuryService->getMaturityLadder($institutionId);

        return response()->json([
            'success' => true,
            'data' => $ladder,
        ]);
    }

    public function bankConcentration(Request $request): JsonResponse
    {
        $user = $request->user();
        $institutionId = $user->isInstitutionUser() ? $user->institution_id : $request->query('institution_id');

        $concentration = $this->treasuryService->getBankConcentration($institutionId);

        return response()->json([
            'success' => true,
            'data' => $concentration,
        ]);
    }

    public function forecast(Request $request): JsonResponse
    {
        $user = $request->user();
        $institutionId = $user->isInstitutionUser() ? $user->institution_id : $request->query('institution_id');

        $forecast = $this->treasuryService->get12MonthForecast($institutionId);

        return response()->json([
            'success' => true,
            'data' => $forecast,
        ]);
    }
}

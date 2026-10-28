<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    protected ReminderService $reminderService;

    public function __construct(ReminderService $reminderService)
    {
        $this->reminderService = $reminderService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $institutionId = $user->isInstitutionUser() ? $user->institution_id : null;

        $reminders = $this->reminderService->getReminders($institutionId);

        return response()->json([
            'success' => true,
            'data' => $reminders,
        ]);
    }

    public function triggerCheck(): JsonResponse
    {
        $result = $this->reminderService->generateScheduledReminders();

        return response()->json([
            'success' => true,
            'message' => 'Maturity interval and escalation scan completed.',
            'data' => $result,
        ]);
    }
}

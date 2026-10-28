<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InstitutionController;
use App\Http\Controllers\Api\FdrController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\OcrController;
use App\Http\Controllers\Api\VerificationCaseController;
use App\Http\Controllers\Api\BankVerificationController;
use App\Http\Controllers\Api\LifecycleController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TreasuryController;
use App\Http\Controllers\Api\ReconciliationController;
use App\Http\Controllers\Api\RenewalController;
use App\Http\Controllers\Api\ReminderController;

Route::prefix('v1')->group(function () {
    // Health Check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'service' => 'AICTE FDR Portal Backend API',
        ]);
    });

    // Public Auth
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected Routes (Sanctum)
    Route::middleware('auth:sanctum')->group(function () {
        // User Profile & Session
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        // Institutions
        Route::get('/institutions', [InstitutionController::class, 'index']);
        Route::post('/institutions', [InstitutionController::class, 'store']);
        Route::get('/institutions/{id}', [InstitutionController::class, 'show']);
        Route::patch('/institutions/{id}', [InstitutionController::class, 'update']);
        Route::get('/institutions/{id}/users', [InstitutionController::class, 'users']);

        // FDR Registry
        Route::get('/fdrs', [FdrController::class, 'index']);
        Route::post('/fdrs', [FdrController::class, 'store']);
        Route::get('/fdrs/{id}', [FdrController::class, 'show']);
        Route::patch('/fdrs/{id}', [FdrController::class, 'update']);
        Route::post('/fdrs/{id}/submit', [FdrController::class, 'submit']);
        Route::post('/fdrs/{id}/validate', [FdrController::class, 'validateRun']);
        Route::get('/fdrs/{id}/history', [FdrController::class, 'history']);
        Route::get('/fdrs/{id}/discrepancies', [FdrController::class, 'discrepancies']);
        Route::post('/fdrs/{id}/discrepancies/{discrepancyId}/resolve', [FdrController::class, 'resolveDiscrepancy']);

        // Documents & Envelope Encryption
        Route::post('/fdrs/{id}/documents', [DocumentController::class, 'upload']);
        Route::get('/documents/{id}/download', [DocumentController::class, 'download']);
        Route::get('/documents/{id}/integrity', [DocumentController::class, 'verifyIntegrity']);

        // OCR Field Extractions & Corrections
        Route::post('/ocr/scan-preview', [OcrController::class, 'scanPreview']);
        Route::get('/fdrs/{id}/ocr-results', [OcrController::class, 'getResults']);
        Route::post('/fdrs/{id}/ocr-results/{ocrId}/corrections', [OcrController::class, 'submitCorrections']);

        // Verification Case Management
        Route::get('/verification-cases', [VerificationCaseController::class, 'index']);
        Route::get('/verification-cases/{id}', [VerificationCaseController::class, 'show']);
        Route::post('/verification-cases/{id}/assign', [VerificationCaseController::class, 'assign']);
        Route::post('/verification-cases/{id}/clarify', [VerificationCaseController::class, 'clarify']);
        Route::post('/verification-cases/{id}/recommend', [VerificationCaseController::class, 'recommend']);
        Route::post('/verification-cases/{id}/approve', [VerificationCaseController::class, 'approve']);
        Route::post('/verification-cases/{id}/reject', [VerificationCaseController::class, 'reject']);

        // Bank Verification Adapter
        Route::post('/fdrs/{id}/bank-verification', [BankVerificationController::class, 'initiate']);
        Route::get('/bank-verifications/{id}', [BankVerificationController::class, 'show']);

        // Lifecycle & Maturity
        Route::get('/lifecycle/maturity-calendar', [LifecycleController::class, 'maturityCalendar']);
        Route::post('/fdrs/{id}/request-release', [LifecycleController::class, 'requestRelease']);
        Route::post('/lifecycle/releases/{id}/approve', [LifecycleController::class, 'approveRelease']);

        // Dashboards & Reports
        Route::get('/reports/summary', [ReportController::class, 'summary']);
        Route::get('/reports/export-csv', [ReportController::class, 'exportCsv']);

        // Audit Trail & Cryptographic Chain Verification
        Route::get('/audit-events/verify-chain', [AuditController::class, 'verifyChain']);
        Route::get('/audit-events', [AuditController::class, 'index']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        // Treasury & Financial Monitoring
        Route::get('/treasury/metrics', [TreasuryController::class, 'metrics']);
        Route::get('/treasury/maturity-ladder', [TreasuryController::class, 'maturityLadder']);
        Route::get('/treasury/bank-concentration', [TreasuryController::class, 'bankConcentration']);
        Route::get('/treasury/forecast', [TreasuryController::class, 'forecast']);

        // Bank Statement & Ledger Reconciliation
        Route::get('/reconciliation', [ReconciliationController::class, 'index']);
        Route::get('/reconciliation/{id}', [ReconciliationController::class, 'show']);
        Route::post('/reconciliation/import', [ReconciliationController::class, 'import']);
        Route::post('/reconciliation/discrepancies/{id}/resolve', [ReconciliationController::class, 'resolve']);

        // Maturity & Renewal Planning
        Route::get('/renewals', [RenewalController::class, 'index']);
        Route::get('/renewals/scenarios/{fdrId}', [RenewalController::class, 'scenarios']);
        Route::post('/renewals/request/{fdrId}', [RenewalController::class, 'requestRenewal']);
        Route::post('/renewals/{renewalId}/approve', [RenewalController::class, 'approveRenewal']);

        // Smart Reminders & Escalation
        Route::get('/reminders', [ReminderController::class, 'index']);
        Route::post('/reminders/trigger', [ReminderController::class, 'triggerCheck']);
    });
});

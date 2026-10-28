<?php

namespace App\Services;

use App\Models\Fdr;
use App\Models\User;
use App\Models\FdrStatusHistory;
use App\Models\VerificationCase;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Illuminate\Support\Str;

class FdrLifecycleService
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Submit a draft FDR for regulatory review.
     */
    public function submitFdr(Fdr $fdr, User $actor): Fdr
    {
        if ($fdr->lifecycle_status !== 'draft' && $fdr->lifecycle_status !== 'resubmitted') {
            throw new RuntimeException("FDR can only be submitted from draft or resubmitted status.");
        }

        return DB::transaction(function () use ($fdr, $actor) {
            $fromStatus = $fdr->lifecycle_status;
            $fdr->update([
                'lifecycle_status' => 'submitted',
                'last_updated_by' => $actor->id,
            ]);

            FdrStatusHistory::create([
                'fdr_id' => $fdr->id,
                'from_status' => $fromStatus,
                'to_status' => 'submitted',
                'actor_id' => $actor->id,
                'action' => 'SUBMIT_FDR',
                'comments' => 'FDR submitted by institution for AICTE verification.',
            ]);

            // Auto-create or activate Verification Case
            VerificationCase::firstOrCreate(
                ['fdr_id' => $fdr->id],
                [
                    'case_number' => 'CASE-' . date('Y') . '-' . strtoupper(Str::random(6)),
                    'institution_id' => $fdr->institution_id,
                    'priority' => 'normal',
                    'status' => 'open',
                    'due_date' => now()->addDays(14)->toDateString(),
                ]
            );

            // Audit
            $this->auditService->log('SUBMIT_FDR', 'Fdr', (string) $fdr->id, [
                'from' => $fromStatus,
                'to' => 'submitted',
            ], 'success', $actor);

            return $fdr;
        });
    }

    /**
     * Assign a Verification Officer to the FDR case.
     */
    public function assignOfficer(Fdr $fdr, User $officer, User $assigner): VerificationCase
    {
        return DB::transaction(function () use ($fdr, $officer, $assigner) {
            $case = VerificationCase::firstOrCreate(
                ['fdr_id' => $fdr->id],
                [
                    'case_number' => 'CASE-' . date('Y') . '-' . strtoupper(Str::random(6)),
                    'institution_id' => $fdr->institution_id,
                    'priority' => 'normal',
                    'due_date' => now()->addDays(14)->toDateString(),
                ]
            );

            $case->update([
                'assigned_officer_id' => $officer->id,
                'assigned_by' => $assigner->id,
                'status' => 'under_review',
            ]);

            $fdr->update(['lifecycle_status' => 'under_review']);

            FdrStatusHistory::create([
                'fdr_id' => $fdr->id,
                'from_status' => 'submitted',
                'to_status' => 'under_review',
                'actor_id' => $assigner->id,
                'action' => 'ASSIGN_CASE',
                'comments' => "Case assigned to Verification Officer: {$officer->name}",
            ]);

            $this->auditService->log('ASSIGN_CASE', 'VerificationCase', (string) $case->id, [
                'officer_id' => $officer->id,
                'fdr_id' => $fdr->id,
            ], 'success', $assigner);

            return $case;
        });
    }

    /**
     * Officer submits recommendation (approve/reject/clarification).
     */
    public function submitRecommendation(Fdr $fdr, User $officer, string $recommendation, ?string $notes = null): VerificationCase
    {
        $case = $fdr->verificationCase;
        if (!$case) {
            throw new RuntimeException("No verification case exists for this FDR.");
        }

        return DB::transaction(function () use ($fdr, $case, $officer, $recommendation, $notes) {
            $case->update([
                'recommendation' => $recommendation,
                'recommendation_notes' => $notes,
                'status' => $recommendation === 'request_clarification' ? 'clarification_pending' : 'officer_recommended',
            ]);

            if ($recommendation === 'request_clarification') {
                $fdr->update(['lifecycle_status' => 'clarification_requested']);
                Notification::create([
                    'institution_id' => $fdr->institution_id,
                    'title' => 'Clarification Required on FDR Submission',
                    'message' => $notes ?? 'Verification Officer has requested clarification on your FDR documents.',
                    'type' => 'action_required',
                    'action_url' => "/fdrs/{$fdr->id}",
                ]);
            }

            $this->auditService->log('OFFICER_RECOMMENDATION', 'VerificationCase', (string) $case->id, [
                'recommendation' => $recommendation,
                'notes' => $notes,
            ], 'success', $officer);

            return $case;
        });
    }

    /**
     * Approving Authority renders final decision (Approve or Reject).
     * Strictly enforces Separation of Duties: Submitter cannot approve!
     */
    public function renderDecision(Fdr $fdr, User $authority, string $decision, ?string $comments = null): Fdr
    {
        // SEPARATION OF DUTIES ENFORCEMENT
        if ($fdr->created_by === $authority->id) {
            throw new RuntimeException("Separation of Duties Violation: Submitter cannot approve or decide on their own FDR submission.");
        }

        if ($decision === 'approved' && $fdr->documents()->count() === 0) {
            throw new RuntimeException("Cannot approve FDR: No physical FDR receipt document has been uploaded for this record.");
        }

        if (!in_array($authority->role, ['approving_authority', 'aicte_admin', 'system_admin'], true)) {
            throw new RuntimeException("Unauthorized: User lacks approving authority privileges.");
        }

        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new RuntimeException("Invalid decision state: must be approved or rejected.");
        }

        return DB::transaction(function () use ($fdr, $authority, $decision, $comments) {
            $fromStatus = $fdr->lifecycle_status;
            $fdr->update([
                'lifecycle_status' => $decision,
                'last_updated_by' => $authority->id,
            ]);

            if ($case = $fdr->verificationCase) {
                $case->update(['status' => 'closed']);
            }

            FdrStatusHistory::create([
                'fdr_id' => $fdr->id,
                'from_status' => $fromStatus,
                'to_status' => $decision,
                'actor_id' => $authority->id,
                'action' => strtoupper($decision) . '_DECISION',
                'comments' => $comments ?? "FDR has been {$decision} by AICTE Approving Authority.",
            ]);

            Notification::create([
                'institution_id' => $fdr->institution_id,
                'title' => "FDR Submission " . ucfirst($decision),
                'message' => "Your FDR (#{$fdr->fdr_number}) has been {$decision} by AICTE. Remarks: " . ($comments ?? 'None'),
                'type' => $decision === 'approved' ? 'success' : 'warning',
                'action_url' => "/fdrs/{$fdr->id}",
            ]);

            $this->auditService->log('FINAL_DECISION', 'Fdr', (string) $fdr->id, [
                'decision' => $decision,
                'comments' => $comments,
                'from_status' => $fromStatus,
            ], 'success', $authority);

            return $fdr;
        });
    }
}

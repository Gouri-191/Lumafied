<?php

namespace App\Services;

use App\Models\Fdr;
use App\Models\FdrReminder;
use App\Models\User;
use Carbon\Carbon;

class ReminderService
{
    protected array $standardIntervals = [90, 60, 30, 15, 7, 1];

    /**
     * Scan active FDRs and generate maturity/renewal reminders according to configured intervals.
     */
    public function generateScheduledReminders(): array
    {
        $today = Carbon::today();
        $activeFdrs = Fdr::whereIn('lifecycle_status', ['active', 'submitted', 'verified', 'approved', 'maturing'])
            ->with('institution')
            ->get();

        $generatedCount = 0;

        foreach ($activeFdrs as $fdr) {
            $maturityDate = Carbon::parse($fdr->maturity_date);
            $daysToMaturity = (int) $today->diffInDays($maturityDate, false);

            // 1. Check standard pre-maturity intervals
            foreach ($this->standardIntervals as $interval) {
                if ($daysToMaturity <= $interval && $daysToMaturity > ($interval - 15)) {
                    $reminderType = "maturity_{$interval}d";
                    $existing = FdrReminder::where('fdr_id', $fdr->id)
                        ->where('reminder_type', $reminderType)
                        ->first();

                    if (!$existing) {
                        FdrReminder::create([
                            'fdr_id' => $fdr->id,
                            'institution_id' => $fdr->institution_id,
                            'reminder_type' => $reminderType,
                            'target_date' => $maturityDate,
                            'status' => 'pending',
                            'priority' => $interval <= 7 ? 'urgent' : ($interval <= 30 ? 'high' : 'medium'),
                            'recipient_email' => $fdr->institution ? $fdr->institution->contact_email : 'admin@aicte-india.org',
                            'title' => "FDR Matures in {$interval} Days: #{$fdr->fdr_number}",
                            'message' => "FDR #{$fdr->fdr_number} ({$fdr->bank_name}) with principal INR {$fdr->principal_amount} matures on {$maturityDate->format('d M Y')}. Initiate renewal or release request.",
                        ]);
                        $generatedCount++;
                    }
                }
            }

            // 2. Check overdue triggers
            if ($daysToMaturity < 0) {
                $existingOverdue = FdrReminder::where('fdr_id', $fdr->id)
                    ->where('reminder_type', 'post_maturity_overdue')
                    ->first();

                if (!$existingOverdue) {
                    FdrReminder::create([
                        'fdr_id' => $fdr->id,
                        'institution_id' => $fdr->institution_id,
                        'reminder_type' => 'post_maturity_overdue',
                        'target_date' => $maturityDate,
                        'status' => 'pending',
                        'priority' => 'urgent',
                        'recipient_email' => $fdr->institution ? $fdr->institution->contact_email : 'admin@aicte-india.org',
                        'title' => "CRITICAL OVERDUE: FDR #{$fdr->fdr_number}",
                        'message' => "FDR #{$fdr->fdr_number} matured on {$maturityDate->format('d M Y')} and is now overdue for renewal or regulatory discharge.",
                    ]);
                    $generatedCount++;
                }
            }
        }

        $escalatedCount = $this->escalateOverdueReminders();

        return [
            'new_reminders_generated' => $generatedCount,
            'escalated_to_supervisors' => $escalatedCount,
        ];
    }

    /**
     * Escalate overdue reminders to regional or national supervisor.
     */
    public function escalateOverdueReminders(): int
    {
        $supervisor = User::whereIn('role', ['system_admin', 'aicte_admin', 'verification_officer'])->first();
        if (!$supervisor) {
            return 0;
        }

        $overdueReminders = FdrReminder::where('reminder_type', 'post_maturity_overdue')
            ->whereNull('escalated_to_id')
            ->get();

        foreach ($overdueReminders as $reminder) {
            $reminder->update([
                'escalated_to_id' => $supervisor->id,
                'escalated_at' => Carbon::now(),
                'status' => 'escalated',
            ]);
        }

        return $overdueReminders->count();
    }

    /**
     * Get reminders with optional filter.
     */
    public function getReminders(?int $institutionId = null): array
    {
        $query = FdrReminder::with(['fdr.institution', 'escalatedTo'])->orderBy('target_date', 'asc');
        if ($institutionId) {
            $query->where('institution_id', $institutionId);
        }

        return $query->get()->toArray();
    }
}

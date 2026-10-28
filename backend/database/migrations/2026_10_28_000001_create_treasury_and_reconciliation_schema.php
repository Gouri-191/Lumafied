<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extend fdrs table with financial treasury & lifecycle fields
        Schema::table('fdrs', function (Blueprint $table) {
            $table->string('interest_type', 40)->default('simple_annual')->after('interest_rate');
            $table->decimal('expected_interest_amount', 15, 2)->nullable()->after('principal_amount');
            $table->decimal('expected_maturity_amount', 15, 2)->nullable()->after('expected_interest_amount');
            $table->decimal('confirmed_maturity_amount', 15, 2)->nullable()->after('expected_maturity_amount');
            $table->decimal('released_amount', 15, 2)->default(0.00)->after('confirmed_maturity_amount');
            $table->string('encumbrance_status', 50)->default('lien_marked_aicte')->after('purpose');
            $table->string('renewal_status', 50)->default('not_required')->after('verification_status');
            $table->unsignedBigInteger('parent_fdr_id')->nullable()->after('renewal_status');
            $table->integer('version')->default(1)->after('parent_fdr_id');
            $table->timestamp('last_verified_at')->nullable()->after('version');
            $table->date('next_action_date')->nullable()->after('last_verified_at');
            $table->unsignedBigInteger('responsible_officer_id')->nullable()->after('next_action_date');

            $table->index('encumbrance_status');
            $table->index('renewal_status');
            $table->index('parent_fdr_id');
            $table->index('next_action_date');
        });

        // 2. FDR Renewals Table (Linked Versioning Engine)
        Schema::create('fdr_renewals', function (Blueprint $table) {
            $table->id();
            $table->string('request_reference', 64)->unique();
            $table->unsignedBigInteger('original_fdr_id');
            $table->unsignedBigInteger('new_fdr_id')->nullable();
            $table->unsignedBigInteger('institution_id');
            $table->integer('proposed_tenure_months');
            $table->decimal('proposed_interest_rate', 5, 2);
            $table->string('proposed_interest_type', 40)->default('simple_annual');
            $table->decimal('estimated_new_maturity_amount', 15, 2);
            $table->text('reason')->nullable();
            $table->string('status', 40)->default('pending_review'); // pending_review, officer_recommended, approved, rejected
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('recommended_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->date('effective_date')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();

            $table->foreign('original_fdr_id')->references('id')->on('fdrs')->onDelete('cascade');
            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');
            $table->index(['original_fdr_id', 'status']);
        });

        // 3. Smart Reminders & Escalation Engine Table
        Schema::create('fdr_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fdr_id');
            $table->unsignedBigInteger('institution_id');
            $table->string('reminder_type', 60); // maturity_90d, maturity_60d, maturity_30d, maturity_15d, maturity_7d, maturity_1d, post_maturity_overdue, compliance_periodic, renewal_pending
            $table->date('target_date');
            $table->string('status', 30)->default('pending'); // pending, delivered, escalated, resolved
            $table->string('priority', 20)->default('medium'); // low, medium, high, urgent
            $table->string('recipient_email', 150);
            $table->string('title', 200);
            $table->text('message');
            $table->unsignedBigInteger('escalated_to_id')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('fdr_id')->references('id')->on('fdrs')->onDelete('cascade');
            $table->index(['fdr_id', 'reminder_type', 'target_date'], 'fdr_rem_type_date_idx');
            $table->index('status');
            $table->index('priority');
        });

        // 4. Reconciliation Runs Table
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_reference', 64)->unique();
            $table->string('bank_name', 150);
            $table->date('statement_period_start')->nullable();
            $table->date('statement_period_end')->nullable();
            $table->integer('total_statement_records')->default(0);
            $table->integer('matched_count')->default(0);
            $table->integer('discrepancy_count')->default(0);
            $table->decimal('total_statement_amount', 15, 2)->default(0.00);
            $table->decimal('total_matched_amount', 15, 2)->default(0.00);
            $table->string('status', 30)->default('completed'); // completed, needs_review
            $table->unsignedBigInteger('performed_by');
            $table->timestamps();

            $table->index('bank_name');
            $table->index('status');
        });

        // 5. Bank Statement Entries Table
        Schema::create('bank_statement_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reconciliation_run_id');
            $table->string('bank_name', 150);
            $table->string('account_no', 100)->nullable();
            $table->string('fdr_reference', 100)->nullable();
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->string('entry_type', 40)->default('fdr_deposit'); // fdr_deposit, maturity_credit, interest_credit
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2)->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('matched_fdr_id')->nullable();
            $table->string('match_status', 30)->default('unmatched'); // matched, unmatched, discrepant
            $table->timestamps();

            $table->foreign('reconciliation_run_id')->references('id')->on('reconciliation_runs')->onDelete('cascade');
            $table->index('fdr_reference');
            $table->index('match_status');
        });

        // 6. Reconciliation Discrepancies Table
        Schema::create('reconciliation_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reconciliation_run_id');
            $table->unsignedBigInteger('fdr_id')->nullable();
            $table->unsignedBigInteger('bank_statement_entry_id')->nullable();
            $table->string('discrepancy_type', 60); // AMOUNT_MISMATCH, UNMATCHED_STATEMENT_ENTRY, MISSING_BANK_ENTRY, MATURITY_PROCEEDS_DIFFERENCE
            $table->decimal('recorded_amount', 15, 2)->nullable();
            $table->decimal('bank_evidenced_amount', 15, 2)->nullable();
            $table->decimal('difference_amount', 15, 2)->nullable();
            $table->string('status', 30)->default('open'); // open, investigating, resolved
            $table->text('investigation_notes')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('reconciliation_run_id')->references('id')->on('reconciliation_runs')->onDelete('cascade');
            $table->index('status');
            $table->index('discrepancy_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_discrepancies');
        Schema::dropIfExists('bank_statement_entries');
        Schema::dropIfExists('reconciliation_runs');
        Schema::dropIfExists('fdr_reminders');
        Schema::dropIfExists('fdr_renewals');

        Schema::table('fdrs', function (Blueprint $table) {
            $table->dropColumn([
                'interest_type',
                'expected_interest_amount',
                'expected_maturity_amount',
                'confirmed_maturity_amount',
                'released_amount',
                'encumbrance_status',
                'renewal_status',
                'parent_fdr_id',
                'version',
                'last_verified_at',
                'next_action_date',
                'responsible_officer_id',
            ]);
        });
    }
};

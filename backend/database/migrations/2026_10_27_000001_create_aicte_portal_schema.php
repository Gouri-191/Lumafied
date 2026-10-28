<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update users table with role, status, institution_id, phone, designation
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('institution_submitter')->after('email');
            $table->string('status', 30)->default('active')->after('role');
            $table->unsignedBigInteger('institution_id')->nullable()->after('status');
            $table->string('phone', 30)->nullable()->after('institution_id');
            $table->string('designation', 100)->nullable()->after('phone');
            $table->index(['institution_id', 'role']);
        });

        // 2. Institutions
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->string('aicte_id', 50)->unique();
            $table->string('name', 200);
            $table->string('category', 100)->default('Engineering & Technology');
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('pincode', 20)->nullable();
            $table->string('contact_email', 150);
            $table->string('contact_phone', 30)->nullable();
            $table->string('verification_status', 30)->default('verified');
            $table->timestamps();
            $table->softDeletes();
        });

        // 3. FDRs
        Schema::create('fdrs', function (Blueprint $table) {
            $table->id();
            $table->string('fdr_tracking_no', 50)->unique();
            $table->unsignedBigInteger('institution_id');
            $table->string('bank_name', 150);
            $table->string('branch_name', 150);
            $table->string('ifsc_code', 30)->nullable();
            $table->string('fdr_number', 100);
            $table->string('account_number_ref', 100)->nullable();
            $table->decimal('principal_amount', 15, 2);
            $table->string('currency', 10)->default('INR');
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->date('issue_date');
            $table->date('maturity_date');
            $table->integer('tenure_months')->default(120);
            $table->string('holder_name', 150);
            $table->string('beneficiary_name', 150);
            $table->string('purpose', 200)->default('AICTE Statutory Security Deposit');
            $table->string('lifecycle_status', 40)->default('draft');
            $table->string('verification_status', 40)->default('pending');
            $table->string('risk_level', 30)->default('low');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('last_updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['institution_id', 'lifecycle_status']);
            $table->index(['fdr_number', 'bank_name']);
            $table->index('maturity_date');
            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');
        });

        // 4. Documents with Envelope Encryption Metadata
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('fdr_id')->nullable();
            $table->unsignedBigInteger('institution_id');
            $table->string('document_type', 50)->default('fdr_original');
            $table->string('original_filename', 255);
            $table->string('storage_path', 255);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('mime_type', 100);
            $table->string('file_hash_sha256', 64);
            $table->string('encryption_algorithm', 40)->default('AES-256-GCM');
            $table->text('encrypted_dek'); // Wrapped DEK
            $table->string('nonce', 64); // GCM 12-byte IV (Base64)
            $table->string('auth_tag', 64); // GCM 16-byte Auth Tag (Base64)
            $table->string('kek_version', 20)->default('v1');
            $table->boolean('is_quarantined')->default(false);
            $table->string('scan_status', 40)->default('clean');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['fdr_id', 'document_type']);
            $table->index('file_hash_sha256');
            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');
        });

        // 5. OCR Extractions
        Schema::create('ocr_extractions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('fdr_id')->nullable();
            $table->string('status', 30)->default('pending');
            $table->json('extracted_data')->nullable();
            $table->longText('raw_text')->nullable();
            $table->decimal('overall_confidence', 5, 2)->default(0.00);
            $table->json('corrected_data')->nullable();
            $table->unsignedBigInteger('corrected_by')->nullable();
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
        });

        // 6. Validation Runs
        Schema::create('validation_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fdr_id');
            $table->unsignedBigInteger('run_by')->nullable();
            $table->string('status', 40)->default('passed');
            $table->integer('rules_checked_count')->default(0);
            $table->integer('discrepancies_count')->default(0);
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->foreign('fdr_id')->references('id')->on('fdrs')->onDelete('cascade');
        });

        // 7. Discrepancies
        Schema::create('discrepancies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('validation_run_id');
            $table->unsignedBigInteger('fdr_id');
            $table->string('rule_identifier', 80);
            $table->string('category', 50)->default('data_mismatch');
            $table->string('severity', 40)->default('requires_review');
            $table->string('field_name', 80)->nullable();
            $table->text('submitted_value')->nullable();
            $table->text('observed_value')->nullable();
            $table->text('explanation');
            $table->string('resolution_status', 40)->default('open');
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('validation_run_id')->references('id')->on('validation_runs')->onDelete('cascade');
            $table->foreign('fdr_id')->references('id')->on('fdrs')->onDelete('cascade');
            $table->index(['fdr_id', 'resolution_status']);
        });

        // 8. Verification Cases
        Schema::create('verification_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number', 50)->unique();
            $table->unsignedBigInteger('fdr_id');
            $table->unsignedBigInteger('institution_id');
            $table->unsignedBigInteger('assigned_officer_id')->nullable();
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->string('priority', 30)->default('normal');
            $table->string('status', 40)->default('open');
            $table->string('recommendation', 40)->nullable();
            $table->text('recommendation_notes')->nullable();
            $table->text('clarification_text')->nullable();
            $table->text('clarification_response')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->foreign('fdr_id')->references('id')->on('fdrs')->onDelete('cascade');
            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');
            $table->index(['assigned_officer_id', 'status']);
        });

        // 9. Bank Verification Requests
        Schema::create('bank_verification_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fdr_id');
            $table->string('request_reference', 60)->unique();
            $table->string('bank_name', 150);
            $table->string('mode', 30)->default('mock'); // 'mock', 'api'
            $table->string('status', 40)->default('pending');
            $table->json('response_payload')->nullable();
            $table->string('verification_evidence_ref', 100)->nullable();
            $table->boolean('simulated_flag')->default(true);
            $table->unsignedBigInteger('initiated_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('fdr_id')->references('id')->on('fdrs')->onDelete('cascade');
        });

        // 10. FDR Status History (State Machine Audit)
        Schema::create('fdr_status_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fdr_id');
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 80);
            $table->text('comments')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('fdr_id')->references('id')->on('fdrs')->onDelete('cascade');
            $table->index(['fdr_id', 'created_at']);
        });

        // 11. Release Requests
        Schema::create('release_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fdr_id');
            $table->unsignedBigInteger('institution_id');
            $table->string('reason', 255);
            $table->string('status', 40)->default('requested');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('no_dues_certificate_ref', 150)->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();

            $table->foreign('fdr_id')->references('id')->on('fdrs')->onDelete('cascade');
            $table->foreign('institution_id')->references('id')->on('institutions')->onDelete('cascade');
        });

        // 12. Notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('institution_id')->nullable();
            $table->string('title', 150);
            $table->text('message');
            $table->string('type', 30)->default('info');
            $table->string('action_url', 255)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });

        // 13. Append-Only Audit Events
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_uuid', 64)->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('institution_id')->nullable();
            $table->string('actor_email', 150)->nullable();
            $table->string('actor_role', 50)->nullable();
            $table->string('action', 100);
            $table->string('resource_type', 80);
            $table->string('resource_id', 80)->nullable();
            $table->string('ip_address', 50)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('details')->nullable();
            $table->string('status', 30)->default('success');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['resource_type', 'resource_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['institution_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('release_requests');
        Schema::dropIfExists('fdr_status_history');
        Schema::dropIfExists('bank_verification_requests');
        Schema::dropIfExists('verification_cases');
        Schema::dropIfExists('discrepancies');
        Schema::dropIfExists('validation_runs');
        Schema::dropIfExists('ocr_extractions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('fdrs');
        Schema::dropIfExists('institutions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'status', 'institution_id', 'phone', 'designation']);
        });
    }
};

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Fdr extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'fdr_tracking_no',
        'institution_id',
        'bank_name',
        'branch_name',
        'ifsc_code',
        'fdr_number',
        'account_number_ref',
        'principal_amount',
        'currency',
        'interest_rate',
        'issue_date',
        'maturity_date',
        'tenure_months',
        'holder_name',
        'beneficiary_name',
        'purpose',
        'lifecycle_status',
        'verification_status',
        'risk_level',
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
        'created_by',
        'last_updated_by',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'expected_interest_amount' => 'decimal:2',
        'expected_maturity_amount' => 'decimal:2',
        'confirmed_maturity_amount' => 'decimal:2',
        'released_amount' => 'decimal:2',
        'issue_date' => 'date',
        'maturity_date' => 'date',
        'next_action_date' => 'date',
        'last_verified_at' => 'datetime',
        'tenure_months' => 'integer',
        'version' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function ocrExtractions(): HasMany
    {
        return $this->hasMany(OcrExtraction::class);
    }

    public function validationRuns(): HasMany
    {
        return $this->hasMany(ValidationRun::class);
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(Discrepancy::class);
    }

    public function verificationCase(): HasOne
    {
        return $this->hasOne(VerificationCase::class);
    }

    public function bankVerificationRequests(): HasMany
    {
        return $this->hasMany(BankVerificationRequest::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(FdrStatusHistory::class)->orderBy('created_at', 'desc');
    }

    public function releaseRequests(): HasMany
    {
        return $this->hasMany(ReleaseRequest::class);
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(FdrRenewal::class, 'fdr_id')->orderBy('created_at', 'desc');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(FdrReminder::class, 'fdr_id')->orderBy('scheduled_for', 'asc');
    }

    public function parentFdr(): BelongsTo
    {
        return $this->belongsTo(Fdr::class, 'parent_fdr_id');
    }

    public function childFdrs(): HasMany
    {
        return $this->hasMany(Fdr::class, 'parent_fdr_id');
    }

    public function responsibleOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_officer_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FdrRenewal extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_reference',
        'original_fdr_id',
        'new_fdr_id',
        'institution_id',
        'proposed_tenure_months',
        'proposed_interest_rate',
        'proposed_interest_type',
        'estimated_new_maturity_amount',
        'reason',
        'status',
        'requested_by',
        'recommended_by',
        'approved_by',
        'effective_date',
        'decision_notes',
    ];

    protected $casts = [
        'proposed_interest_rate' => 'decimal:2',
        'estimated_new_maturity_amount' => 'decimal:2',
        'effective_date' => 'date',
        'proposed_tenure_months' => 'integer',
    ];

    public function originalFdr(): BelongsTo
    {
        return $this->belongsTo(Fdr::class, 'original_fdr_id');
    }

    public function newFdr(): BelongsTo
    {
        return $this->belongsTo(Fdr::class, 'new_fdr_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

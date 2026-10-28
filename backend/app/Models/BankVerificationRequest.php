<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankVerificationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'fdr_id',
        'request_reference',
        'bank_name',
        'mode',
        'status',
        'response_payload',
        'verification_evidence_ref',
        'simulated_flag',
        'initiated_by',
        'completed_at',
    ];

    protected $casts = [
        'response_payload' => 'array',
        'simulated_flag' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function fdr(): BelongsTo
    {
        return $this->belongsTo(Fdr::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}

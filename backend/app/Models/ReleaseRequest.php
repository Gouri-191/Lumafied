<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'fdr_id',
        'institution_id',
        'reason',
        'status',
        'requested_by',
        'reviewed_by',
        'approved_by',
        'no_dues_certificate_ref',
        'decision_notes',
    ];

    public function fdr(): BelongsTo
    {
        return $this->belongsTo(Fdr::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

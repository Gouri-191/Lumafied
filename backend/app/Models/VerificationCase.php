<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_number',
        'fdr_id',
        'institution_id',
        'assigned_officer_id',
        'assigned_by',
        'priority',
        'status',
        'recommendation',
        'recommendation_notes',
        'clarification_text',
        'clarification_response',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function fdr(): BelongsTo
    {
        return $this->belongsTo(Fdr::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}

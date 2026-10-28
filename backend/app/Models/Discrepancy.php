<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Discrepancy extends Model
{
    use HasFactory;

    protected $fillable = [
        'validation_run_id',
        'fdr_id',
        'rule_identifier',
        'category',
        'severity',
        'field_name',
        'submitted_value',
        'observed_value',
        'explanation',
        'resolution_status',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function validationRun(): BelongsTo
    {
        return $this->belongsTo(ValidationRun::class);
    }

    public function fdr(): BelongsTo
    {
        return $this->belongsTo(Fdr::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}

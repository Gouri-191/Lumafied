<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ValidationRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'fdr_id',
        'run_by',
        'status',
        'rules_checked_count',
        'discrepancies_count',
        'summary',
    ];

    public function fdr(): BelongsTo
    {
        return $this->belongsTo(Fdr::class);
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(Discrepancy::class);
    }

    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by');
    }
}

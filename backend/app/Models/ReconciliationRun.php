<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconciliationRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_reference',
        'bank_name',
        'statement_period_start',
        'statement_period_end',
        'total_statement_records',
        'matched_count',
        'discrepancy_count',
        'total_statement_amount',
        'total_matched_amount',
        'status',
        'performed_by',
    ];

    protected $casts = [
        'statement_period_start' => 'date',
        'statement_period_end' => 'date',
        'total_statement_records' => 'integer',
        'matched_count' => 'integer',
        'discrepancy_count' => 'integer',
        'total_statement_amount' => 'decimal:2',
        'total_matched_amount' => 'decimal:2',
    ];

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function statementEntries(): HasMany
    {
        return $this->hasMany(BankStatementEntry::class);
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(ReconciliationDiscrepancy::class);
    }
}

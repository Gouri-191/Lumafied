<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationDiscrepancy extends Model
{
    use HasFactory;

    protected $fillable = [
        'reconciliation_run_id',
        'fdr_id',
        'bank_statement_entry_id',
        'discrepancy_type',
        'recorded_amount',
        'bank_evidenced_amount',
        'difference_amount',
        'status',
        'investigation_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'recorded_amount' => 'decimal:2',
        'bank_evidenced_amount' => 'decimal:2',
        'difference_amount' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function reconciliationRun(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class);
    }

    public function fdr(): BelongsTo
    {
        return $this->belongsTo(Fdr::class);
    }

    public function statementEntry(): BelongsTo
    {
        return $this->belongsTo(BankStatementEntry::class, 'bank_statement_entry_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}

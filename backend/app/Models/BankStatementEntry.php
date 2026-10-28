<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'reconciliation_run_id',
        'bank_name',
        'account_no',
        'fdr_reference',
        'transaction_date',
        'value_date',
        'entry_type',
        'amount',
        'balance_after',
        'description',
        'matched_fdr_id',
        'match_status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'transaction_date' => 'date',
        'value_date' => 'date',
    ];

    public function reconciliationRun(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class);
    }

    public function matchedFdr(): BelongsTo
    {
        return $this->belongsTo(Fdr::class, 'matched_fdr_id');
    }
}

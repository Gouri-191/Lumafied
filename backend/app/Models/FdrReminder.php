<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FdrReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'fdr_id',
        'institution_id',
        'reminder_type',
        'target_date',
        'status',
        'priority',
        'recipient_email',
        'title',
        'message',
        'escalated_to_id',
        'escalated_at',
        'resolved_at',
    ];

    protected $casts = [
        'target_date' => 'date',
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function fdr(): BelongsTo
    {
        return $this->belongsTo(Fdr::class, 'fdr_id');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to_id');
    }
}

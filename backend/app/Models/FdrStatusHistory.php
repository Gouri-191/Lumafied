<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FdrStatusHistory extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $table = 'fdr_status_history';

    protected $fillable = [
        'fdr_id',
        'from_status',
        'to_status',
        'actor_id',
        'action',
        'comments',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function fdr(): BelongsTo
    {
        return $this->belongsTo(Fdr::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

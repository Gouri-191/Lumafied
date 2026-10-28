<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcrExtraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'fdr_id',
        'status',
        'extracted_data',
        'raw_text',
        'overall_confidence',
        'corrected_data',
        'corrected_by',
        'corrected_at',
    ];

    protected $casts = [
        'extracted_data' => 'array',
        'corrected_data' => 'array',
        'overall_confidence' => 'decimal:2',
        'corrected_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function fdr(): BelongsTo
    {
        return $this->belongsTo(Fdr::class);
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}

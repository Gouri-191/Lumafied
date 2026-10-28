<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'fdr_id',
        'institution_id',
        'document_type',
        'original_filename',
        'storage_path',
        'file_size_bytes',
        'mime_type',
        'file_hash_sha256',
        'bank_sha256',
        'bank_sha_verified',
        'bank_sha_match_status',
        'encryption_algorithm',
        'encrypted_dek',
        'nonce',
        'auth_tag',
        'kek_version',
        'is_quarantined',
        'scan_status',
        'uploaded_by',
    ];

    protected $casts = [
        'is_quarantined' => 'boolean',
        'bank_sha_verified' => 'boolean',
        'file_size_bytes' => 'integer',
    ];

    public function fdr(): BelongsTo
    {
        return $this->belongsTo(Fdr::class);
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function ocrExtraction(): HasOne
    {
        return $this->hasOne(OcrExtraction::class);
    }
}

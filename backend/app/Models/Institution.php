<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'aicte_id',
        'name',
        'category',
        'address',
        'city',
        'state',
        'pincode',
        'contact_email',
        'contact_phone',
        'verification_status',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function fdrs(): HasMany
    {
        return $this->hasMany(Fdr::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}

<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'institution_id',
        'phone',
        'designation',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function isSystemAdmin(): bool
    {
        return $this->role === 'system_admin';
    }

    public function isAicteAdmin(): bool
    {
        return in_array($this->role, ['aicte_admin', 'system_admin'], true);
    }

    public function isVerificationOfficer(): bool
    {
        return $this->role === 'verification_officer';
    }

    public function isApprovingAuthority(): bool
    {
        return $this->role === 'approving_authority';
    }

    public function isAuditor(): bool
    {
        return $this->role === 'auditor';
    }

    public function isInstitutionUser(): bool
    {
        return in_array($this->role, ['institution_admin', 'institution_submitter'], true);
    }

    public function canAccessInstitution(int $institutionId): bool
    {
        if ($this->isAicteAdmin() || $this->isVerificationOfficer() || $this->isApprovingAuthority() || $this->isAuditor()) {
            return true;
        }

        return (int) $this->institution_id === $institutionId;
    }
}

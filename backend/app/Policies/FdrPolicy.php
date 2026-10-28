<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Fdr;
use App\Models\Document;

class FdrPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Filtered by tenant in controller/service
    }

    public function view(User $user, Fdr $fdr): bool
    {
        return $user->canAccessInstitution($fdr->institution_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['institution_submitter', 'institution_admin', 'aicte_admin', 'system_admin'], true);
    }

    public function update(User $user, Fdr $fdr): bool
    {
        if (!$user->canAccessInstitution($fdr->institution_id)) {
            return false;
        }

        // Submitter can only edit drafts or clarification requests
        if ($user->isInstitutionUser()) {
            return in_array($fdr->lifecycle_status, ['draft', 'clarification_requested'], true);
        }

        return $user->isAicteAdmin();
    }

    public function delete(User $user, Fdr $fdr): bool
    {
        if ($user->isInstitutionUser()) {
            return $fdr->lifecycle_status === 'draft';
        }

        return $user->isSystemAdmin();
    }
}

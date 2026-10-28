<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Document;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $user->canAccessInstitution($document->institution_id);
    }

    public function download(User $user, Document $document): bool
    {
        return $user->canAccessInstitution($document->institution_id);
    }

    public function delete(User $user, Document $document): bool
    {
        if ($user->isInstitutionUser()) {
            return $document->fdr && $document->fdr->lifecycle_status === 'draft';
        }

        return $user->isSystemAdmin();
    }
}

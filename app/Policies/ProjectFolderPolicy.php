<?php

namespace App\Policies;

use App\Models\ProjectFolder;
use App\Models\User;

class ProjectFolderPolicy
{
    public function view(User $user, ProjectFolder $folder): bool
    {
        if (! $user->can('view', $folder->project)) {
            return false;
        }

        if ($user->portalRole() === 'client') {
            return $folder->isEffectivelyClientVisible();
        }

        return true;
    }

    public function sign(User $user, ProjectFolder $folder): bool
    {
        return $user->portalRole() === 'client'
            && $this->view($user, $folder)
            && $folder->type === 'file'
            && $folder->resource_type === 'document'
            && (bool) $folder->requires_client_signature;
    }
}

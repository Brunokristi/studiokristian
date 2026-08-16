<?php

namespace App\Policies;

use App\Models\ClientContact;
use App\Models\ProjectFile;
use App\Models\User;

class ProjectFilePolicy
{
    public function view(User|ClientContact $actor, ProjectFile $file): bool
    {
        if ($actor instanceof User) {
            if (! $actor->can('view', $file->project)) {
                return false;
            }

            if ($actor->portalRole() === 'client') {
                return $file->isEffectivelyClientVisible();
            }

            return true;
        }

        $contact = $actor;

        return $file->isEffectivelyClientVisible() && $contact->can('view', $file->project);
    }

    public function download(User|ClientContact $actor, ProjectFile $file): bool
    {
        return $this->view($actor, $file);
    }

    public function upload(User $user, ProjectFile $file): bool
    {
        return in_array($user->portalRole(), ['admin', 'coworker'], true)
            && $user->can('manageFiles', $file->project);
    }

    public function update(User $user, ProjectFile $file): bool
    {
        return in_array($user->portalRole(), ['admin', 'coworker'], true)
            && $user->can('manageFiles', $file->project);
    }

    public function move(User $user, ProjectFile $file): bool
    {
        return $this->update($user, $file);
    }

    public function delete(User $user, ProjectFile $file): bool
    {
        return $this->update($user, $file);
    }
}
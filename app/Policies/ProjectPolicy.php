<?php

namespace App\Policies;

use App\Models\ClientContact;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User|ClientContact $actor, Project $project): bool
    {
        if ($actor instanceof User) {
            if ($actor->portalRole() === 'admin') {
                return true;
            }

            if ($project->archived_at !== null) {
                return false;
            }

            return $actor->projects()->whereKey($project->id)->exists();
        }

        $contact = $actor;

        return $contact->hasPortalAccess()
            && $project->company_id === $contact->company_id
            && $project->company?->status === 'active'
            && $project->archived_at === null
            && $contact->projects()->whereKey($project->id)->exists();
    }

    public function update(User $user, Project $project): bool
    {
        if (! $this->view($user, $project)) {
            return false;
        }

        return in_array($user->portalRole(), ['admin', 'coworker'], true);
    }

    public function manageFiles(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function manageDocuments(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }

    public function createTicket(User $user, Project $project): bool
    {
        return $this->view($user, $project);
    }
}
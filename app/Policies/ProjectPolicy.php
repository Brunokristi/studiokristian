<?php

namespace App\Policies;

use App\Models\ClientContact;
use App\Models\Project;

class ProjectPolicy
{
    public function view(ClientContact $contact, Project $project): bool
    {
        return $contact->hasPortalAccess()
            && $project->company_id === $contact->company_id
            && $project->company?->status === 'active'
            && $project->archived_at === null
            && $contact->projects()->whereKey($project->id)->exists();
    }
}
<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectTicket;
use App\Models\User;

class ProjectTicketPolicy
{
    public function view(User $user, ProjectTicket $ticket): bool
    {
        if (! $user->can('view', $ticket->project)) {
            return false;
        }

        if ($user->portalRole() === 'client') {
            return $ticket->created_by_user_id === $user->id;
        }

        return true;
    }

    public function create(User $user, Project $project): bool
    {
        return $user->can('createTicket', $project);
    }

    public function update(User $user, ProjectTicket $ticket): bool
    {
        return in_array($user->portalRole(), ['admin', 'coworker'], true)
            && $user->can('update', $ticket->project);
    }
}

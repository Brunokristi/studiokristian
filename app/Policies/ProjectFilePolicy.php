<?php

namespace App\Policies;

use App\Models\ClientContact;
use App\Models\ProjectFile;

class ProjectFilePolicy
{
    public function view(ClientContact $contact, ProjectFile $file): bool
    {
        return $file->visibility === 'client' && $contact->can('view', $file->project);
    }
}
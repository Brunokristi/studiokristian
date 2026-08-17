<?php

namespace App\Services;

use App\Models\ClientContact;
use App\Models\Project;
use App\Models\ProjectFolder;
use App\Models\ProjectFolderSignature;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ClientDocumentSignatureService
{
    public function signatureUser(ClientContact $contact): User
    {
        $shadowEmail = $this->shadowEmail($contact);

        $query = User::query();

        if (User::hasClientContactColumn()) {
            $query->where('client_contact_id', $contact->id);
        } else {
            $query->where('email', $shadowEmail);
        }

        $user = $query->first();

        if ($user) {
            return $user;
        }

        $attributes = [
            'name' => $contact->name !== '' ? $contact->name : 'Client Contact '.$contact->id,
            'email' => $shadowEmail,
            'password' => Hash::make(Str::random(40)),
            'is_admin' => false,
        ];

        if (User::hasRoleColumn()) {
            $attributes['role'] = 'client';
        }

        if (User::hasClientContactColumn()) {
            $attributes['client_contact_id'] = $contact->id;
        }

        return User::query()->create($attributes);
    }

    public function pendingSignatureCount(Project $project, ClientContact $contact): int
    {
        $signatureUserId = $this->signatureUser($contact)->id;
        $documents = $this->visibleDocuments($project);

        if ($documents->isEmpty()) {
            return 0;
        }

        $signedFolderIds = $this->signedFolderIds($project, $signatureUserId);

        return $documents
            ->filter(fn (ProjectFolder $folder) => (bool) $folder->requires_client_signature)
            ->filter(fn (ProjectFolder $folder) => ! $signedFolderIds->contains((int) $folder->id))
            ->count();
    }

    public function signedFolderIds(Project $project, int $signatureUserId): Collection
    {
        if (! Schema::hasTable('project_folder_signatures')) {
            return collect();
        }

        return ProjectFolderSignature::query()
            ->where('project_id', $project->id)
            ->where('user_id', $signatureUserId)
            ->pluck('project_folder_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    public function visibleDocuments(Project $project): Collection
    {
        $folders = $project->relationLoaded('folders')
            ? $project->folders
            : $project->folders()->get();

        $folders = $folders->values();
        $byId = $folders->keyBy('id');
        $visibleCache = [];

        return $folders
            ->filter(fn (ProjectFolder $folder) => $folder->type === 'file')
            ->filter(fn (ProjectFolder $folder) => $folder->resource_type === 'document')
            ->filter(fn (ProjectFolder $folder) => $this->isEffectivelyVisible($folder, $byId, $visibleCache))
            ->values();
    }

    private function isEffectivelyVisible(ProjectFolder $folder, Collection $byId, array &$visibleCache): bool
    {
        $folderId = (int) $folder->id;

        if (array_key_exists($folderId, $visibleCache)) {
            return $visibleCache[$folderId];
        }

        if (! (bool) $folder->client_visible) {
            $visibleCache[$folderId] = false;
            return false;
        }

        if (! $folder->parent_id) {
            $visibleCache[$folderId] = true;
            return true;
        }

        $parent = $byId->get((int) $folder->parent_id);

        if (! $parent instanceof ProjectFolder) {
            $visibleCache[$folderId] = false;
            return false;
        }

        $visibleCache[$folderId] = $this->isEffectivelyVisible($parent, $byId, $visibleCache);

        return $visibleCache[$folderId];
    }

    private function shadowEmail(ClientContact $contact): string
    {
        return 'client-contact-'.$contact->id.'@portal.local';
    }
}

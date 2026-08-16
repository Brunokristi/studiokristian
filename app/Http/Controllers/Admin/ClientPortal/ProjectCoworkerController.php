<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\ClientContact;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProjectCoworkerController extends Controller
{
    public function store(Project $project, Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $this->authorizeProjectAccess($request, $project);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:255']]);
        $user = User::firstOrCreate(
            ['email' => strtolower($data['email'])],
            ['name' => $data['name'], 'password' => Hash::make(Str::random(40)), 'is_admin' => false]
        );

        if (! $user->is_admin && User::hasRoleColumn()) {
            $user->forceFill(['role' => 'coworker'])->save();
        }

        if (User::hasProjectUserAccessTypeColumn()) {
            $project->coworkers()->syncWithoutDetaching([$user->id => ['access_type' => 'coworker']]);
        } else {
            $project->coworkers()->syncWithoutDetaching([$user->id]);
        }
        $user->notify(new ProjectInvitationNotification($project, route('login')));
        return response()->json(['id' => $user->id, 'name' => $user->name, 'email' => $user->email], 201);
    }

    public function inviteContact(Project $project, Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $this->authorizeProjectAccess($request, $project);
        $data = $request->validate(['contact_id' => ['required', 'integer']]);
        $contact = $project->company->contacts()->whereKey($data['contact_id'])->firstOrFail();
        $contact->update(['active' => true, 'can_access_portal' => true, 'access_revoked_at' => null]);
        $project->contacts()->syncWithoutDetaching([$contact->id]);
        $contact->notify(new ProjectInvitationNotification($project, route('client.login')));
        return response()->json(['id' => $contact->id, 'name' => $contact->name, 'email' => $contact->email], 201);
    }

    public function resendCoworkerInvitation(Project $project, User $user): JsonResponse
    {
        abort_unless(request()->user()?->is_admin, 403);
        $this->authorizeProjectAccess(request(), $project);
        abort_unless($project->coworkers()->whereKey($user->id)->exists(), 404);

        $user->notify(new ProjectInvitationNotification($project, route('login')));

        return response()->json(['status' => 'resent']);
    }

    public function resendContactInvitation(Project $project, ClientContact $contact): JsonResponse
    {
        abort_unless(request()->user()?->is_admin, 403);
        $this->authorizeProjectAccess(request(), $project);
        abort_unless($project->contacts()->whereKey($contact->id)->exists(), 404);

        $contact->update(['active' => true, 'can_access_portal' => true, 'access_revoked_at' => null]);
        $contact->notify(new ProjectInvitationNotification($project, route('client.login')));

        return response()->json(['status' => 'resent']);
    }

    private function authorizeProjectAccess(Request $request, Project $project): void
    {
        $user = $request->user();

        if ($user?->is_admin) {
            return;
        }

        abort_unless($project->members()->whereKey($user?->id)->exists(), 403);
    }
}
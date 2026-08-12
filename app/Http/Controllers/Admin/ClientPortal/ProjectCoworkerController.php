<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
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
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:255']]);
        $user = User::firstOrCreate(['email' => strtolower($data['email'])], ['name' => $data['name'], 'password' => Hash::make(Str::random(40)), 'is_admin' => false]);
        $project->coworkers()->syncWithoutDetaching([$user->id]);
        $user->notify(new ProjectInvitationNotification($project, route('login')));
        return response()->json(['id' => $user->id, 'name' => $user->name, 'email' => $user->email], 201);
    }

    public function inviteContact(Project $project, Request $request): JsonResponse
    {
        $data = $request->validate(['contact_id' => ['required', 'integer']]);
        $contact = $project->company->contacts()->whereKey($data['contact_id'])->firstOrFail();
        $contact->update(['active' => true, 'can_access_portal' => true, 'access_revoked_at' => null]);
        $project->contacts()->syncWithoutDetaching([$contact->id]);
        $contact->notify(new ProjectInvitationNotification($project, route('client.login')));
        return response()->json(['id' => $contact->id, 'name' => $contact->name, 'email' => $contact->email], 201);
    }
}
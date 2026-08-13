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

class CoworkerController extends Controller
{
    public function index(): JsonResponse
    {
        $coworkers = User::query()
            ->where('is_admin', false)
            ->with(['projects' => fn ($query) => $query->select('projects.id', 'projects.name', 'projects.company_id')])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'project_ids' => $user->projects->pluck('id')->all(),
                'projects' => $user->projects->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                ]),
            ]);

        return response()->json($coworkers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
        ]);

        $user = User::firstOrCreate(
            ['email' => strtolower($data['email'])],
            ['name' => $data['name'], 'password' => Hash::make(Str::random(40)), 'is_admin' => false]
        );

        if (isset($data['project_ids'])) {
            $user->projects()->sync($data['project_ids']);
            foreach ($user->projects()->whereIn('projects.id', $data['project_ids'])->get() as $project) {
                $user->notify(new ProjectInvitationNotification($project, route('login')));
            }
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'project_ids' => $user->projects()->pluck('projects.id')->all(),
        ], 201);
    }
}

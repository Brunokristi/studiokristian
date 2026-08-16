<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PortalUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $role = $this->normalizeRole($request->string('role')->toString() ?: null);

        $query = User::query()
            ->where('is_admin', false)
            ->with(['projects' => fn ($projects) => $projects->select('projects.id', 'projects.name')]);

        if (User::hasRoleColumn()) {
            $query->whereIn('role', ['coworker', 'client']);
        }

        if ($role && User::hasRoleColumn()) {
            $query->where('role', $role);
        }

        return response()->json([
            'data' => $query->orderBy('name')->get()->map(fn (User $user) => $this->payload($user)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $user = User::query()->firstWhere('email', strtolower($data['email']));
        $created = false;

        if (! $user) {
            $created = true;
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => Hash::make(Str::random(40)),
                'is_admin' => false,
            ]);

            if (User::hasRoleColumn()) {
                $user->forceFill(['role' => $data['role']])->save();
            }

            if (User::hasClientContactColumn()) {
                $user->forceFill(['client_contact_id' => $data['client_contact_id'] ?? null])->save();
            }
        } else {
            abort_if($user->is_admin, 422, 'That email belongs to an admin account.');

            $user->update([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
            ]);

            if (User::hasRoleColumn()) {
                $user->forceFill(['role' => $data['role']])->save();
            }

            if (User::hasClientContactColumn()) {
                $user->forceFill(['client_contact_id' => $data['client_contact_id'] ?? null])->save();
            }
        }

        $user->projects()->sync($this->projectSyncMap($data['project_ids'] ?? [], $data['role']));

        return response()->json(['data' => $this->payload($user->fresh('projects'))], $created ? 201 : 200);
    }

    public function update(User $portalUser, Request $request): JsonResponse
    {
        abort_if($portalUser->is_admin, 404);

        $data = $this->validated($request, $portalUser->id);

        $portalUser->update([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
        ]);

        if (User::hasRoleColumn()) {
            $portalUser->forceFill(['role' => $data['role']])->save();
        }

        if (User::hasClientContactColumn()) {
            $portalUser->forceFill(['client_contact_id' => $data['client_contact_id'] ?? null])->save();
        }

        $portalUser->projects()->sync($this->projectSyncMap($data['project_ids'] ?? [], $data['role']));

        return response()->json(['data' => $this->payload($portalUser->fresh('projects'))]);
    }

    public function destroy(User $portalUser): JsonResponse
    {
        abort_if($portalUser->is_admin, 404);

        $portalUser->projects()->detach();
        $portalUser->delete();

        return response()->json(['status' => 'deleted']);
    }

    private function validated(Request $request, ?int $ignoreUserId = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($ignoreUserId)],
            'role' => ['required', Rule::in(['coworker', 'client'])],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
            'client_contact_id' => ['nullable', 'integer', 'exists:client_contacts,id'],
        ]);

        if (($data['role'] ?? '') !== 'client') {
            $data['client_contact_id'] = null;
        }

        return $data;
    }

    private function normalizeRole(?string $role): ?string
    {
        if (! in_array($role, ['coworker', 'client'], true)) {
            return null;
        }

        return $role;
    }

    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->portalRole(),
            'client_contact_id' => User::hasClientContactColumn() ? $user->client_contact_id : null,
            'project_ids' => $user->projects->pluck('id')->values(),
            'projects' => $user->projects->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
            ])->values(),
        ];
    }

    private function projectSyncMap(array $projectIds, string $role): array
    {
        if (! User::hasProjectUserAccessTypeColumn()) {
            return array_values(array_unique(array_map(fn ($id) => (int) $id, $projectIds)));
        }

        $map = [];

        foreach ($projectIds as $projectId) {
            $map[(int) $projectId] = ['access_type' => $role];
        }

        return $map;
    }
}

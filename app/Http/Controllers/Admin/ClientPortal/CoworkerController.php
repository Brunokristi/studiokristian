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
use Illuminate\Validation\Rule;

class CoworkerController extends Controller
{
    public function index(): JsonResponse
    {
        $query = User::query()
            ->where('is_admin', false)
            ->with(['projects' => fn ($query) => $query->select('projects.id', 'projects.name', 'projects.company_id')]);

        $search = trim((string) request('search', ''));

        if ($search !== '') {
            $like = '%'.str_replace('%', '\\%', $search).'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhereHas('projects', fn ($projects) => $projects->where('name', 'like', $like));
            });
        }

        $sort = request('sort', 'name');
        $direction = request('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, ['name', 'email', 'updated_at'], true)) {
            $sort = 'name';
        }

        $query->orderBy($sort, $direction)->orderBy('id', 'asc');

        $paginated = $query->paginate((int) request('per_page', 25));

        $paginated->setCollection(
            $paginated->getCollection()->map(fn (User $user) => $this->payload($user->load(['projects']))['data'])
        );

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'current_user' => request()->user() ? [
                'id' => request()->user()->id,
                'name' => request()->user()->name,
                'email' => request()->user()->email,
                'is_admin' => (bool) request()->user()->is_admin,
            ] : null,
        ]);
    }

    public function show(User $coworker): JsonResponse
    {
        abort_if($coworker->is_admin, 404);

        $coworker->load(['projects' => fn ($query) => $query->select('projects.id', 'projects.name', 'projects.company_id')]);

        return response()->json([
            'data' => [
                'id' => $coworker->id,
                'name' => $coworker->name,
                'email' => $coworker->email,
                'project_ids' => $coworker->projects->pluck('id')->all(),
                'projects' => $coworker->projects->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                ]),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
        ]);

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
        } else {
            abort_if($user->is_admin, 422, 'That email belongs to an admin account and cannot be converted into a coworker.');

            $user->update([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
            ]);
        }

        $newProjectIds = array_values(array_unique($data['project_ids'] ?? []));
        $existingProjectIds = $user->projects()->pluck('projects.id')->all();
        $addedProjectIds = array_values(array_diff($newProjectIds, $existingProjectIds));

        if (isset($data['project_ids'])) {
            $user->projects()->sync($newProjectIds);

            foreach ($user->projects()->whereIn('projects.id', $addedProjectIds)->get() as $project) {
                $user->notify(new ProjectInvitationNotification($project, route('login')));
            }
        }

        return response()->json($this->payload($user->fresh(['projects'])), $created ? 201 : 200);
    }

    public function update(User $coworker, Request $request): JsonResponse
    {
        abort_if($coworker->is_admin, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($coworker->id)],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
        ]);

        $coworker->update([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
        ]);

        $newProjectIds = array_values(array_unique($data['project_ids'] ?? []));
        $existingProjectIds = $coworker->projects()->pluck('projects.id')->all();
        $addedProjectIds = array_values(array_diff($newProjectIds, $existingProjectIds));

        $coworker->projects()->sync($newProjectIds);

        foreach ($coworker->projects()->whereIn('projects.id', $addedProjectIds)->get() as $project) {
            $coworker->notify(new ProjectInvitationNotification($project, route('login')));
        }

        return response()->json($this->payload($coworker->fresh(['projects'])));
    }

    public function destroy(User $coworker): JsonResponse
    {
        abort_if($coworker->is_admin, 404);

        $coworker->projects()->detach();
        $coworker->delete();

        return response()->json(['status' => 'deleted']);
    }

    private function payload(User $user): array
    {
        return [
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'project_ids' => $user->projects->pluck('id')->all(),
                'projects' => $user->projects->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                ]),
            ],
        ];
    }
}

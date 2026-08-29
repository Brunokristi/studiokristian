<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectRequest;
use App\Http\Resources\Admin\ProjectResource;
use App\Models\ClientContact;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectInvitationNotification;
use App\Services\ProjectInstantiationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $sort = in_array(
            $request->string('sort')->toString(),
            [
                'name',
                'portal_status',
                'updated_at',
            ],
            true
        )
            ? $request->string('sort')->toString()
            : 'updated_at';

        $direction =
            $request->string('direction')->toString() === 'asc'
                ? 'asc'
                : 'desc';

        $search =
            trim(
                $request->string('search')->toString()
            );

        $query = Project::query()
            ->whereNotNull('company_id')
            ->with([
                'company',
                'serviceProduct',
            ])
            ->withCount('contacts')
            ->when(
                $search !== '',
                fn ($query) =>
                    $query->where(
                        fn ($nested) =>
                            $nested
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'project_code',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhereHas(
                                    'company',
                                    fn ($company) =>
                                        $company->where(
                                            'name',
                                            'like',
                                            "%{$search}%"
                                        )
                                )
                    )
            )
            ->when(
                $request->filled('status'),
                fn ($query) =>
                    $query->where(
                        'portal_status',
                        $request->string('status')
                    )
            )
            ->when(
                $request->integer('company_id'),
                fn ($query, $id) =>
                    $query->where(
                        'company_id',
                        $id
                    )
            )
            ->when(
                $request->integer('service_product_id'),
                fn ($query, $id) =>
                    $query->where(
                        'service_product_id',
                        $id
                    )
            )
            ->orderBy(
                $sort,
                $direction
            );

        if (! $request->user()?->is_admin) {
            $query->whereHas(
                'members',
                fn ($members) =>
                    $members->whereKey(
                        $request->user()->id
                    )
            );
        }

        return ProjectResource::collection(
            $query->paginate(
                min(
                    max(
                        $request->integer(
                            'per_page',
                            25
                        ),
                        10
                    ),
                    100
                )
            )
        );
    }

    public function store(
        StoreProjectRequest $request,
        ProjectInstantiationService $service
    ): JsonResponse {
        abort_unless(
            $request->user()?->is_admin,
            403
        );

        $project = $service->create(
            $request->validated(),
            $request->user()
        );

        return (
            new ProjectResource(
                $project->load([
                    'company',
                    'serviceProduct',
                    'contacts',
                    'coworkers',
                ])->loadCount('contacts')
            )
        )
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Project $project
    ): ProjectResource {
        abort_if(
            $project->company_id === null,
            404
        );

        $this->authorizeProjectAccess(
            request(),
            $project
        );

        return new ProjectResource(
            $project
                ->load([
                    'company',
                    'serviceProduct',
                    'contacts',
                    'coworkers',
                    'folders.signatures',
                ])
                ->loadCount('contacts')
        );
    }

    public function update(
        StoreProjectRequest $request,
        Project $project
    ): ProjectResource {
        $this->authorizeProjectAccess(
            $request,
            $project
        );

        if (! $request->user()?->is_admin) {
            return new ProjectResource(
                $project
                    ->fresh()
                    ->load([
                        'company',
                        'serviceProduct',
                        'contacts',
                        'coworkers',
                    ])
                    ->loadCount('contacts')
            );
        }

        $currentContactIds =
            $project
                ->contacts()
                ->pluck('client_contacts.id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $currentCoworkerIds =
            $project
                ->coworkers()
                ->pluck('users.id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $currentAdminId =
            $request->user()?->is_admin
                ? (int) $request->user()->id
                : null;

        $nextContactIds =
            collect(
                $request->validated(
                    'contact_ids',
                    []
                )
            )
                ->map(fn ($id) => (int) $id)
                ->all();

        $nextCoworkerIds =
            collect(
                $request->validated(
                    'coworker_ids',
                    []
                )
            )
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

        if ($currentAdminId) {
            $nextCoworkerIds =
                $nextCoworkerIds
                    ->push($currentAdminId)
                    ->unique()
                    ->values();
        }

        $nextCoworkerIds =
            $nextCoworkerIds->all();

        $addedContactIds =
            array_values(
                array_diff(
                    $nextContactIds,
                    $currentContactIds
                )
            );

        $addedCoworkerIds =
            array_values(
                array_diff(
                    $nextCoworkerIds,
                    $currentCoworkerIds
                )
            );

        DB::transaction(
            function () use (
                $request,
                $project,
                $nextCoworkerIds
            ) {
                $data =
                    $request
                        ->safe()
                        ->except([
                            'contact_ids',
                            'coworker_ids',
                        ]);

                $data['url'] =
                    isset($data['url']) &&
                    $data['url'] !== ''
                        ? $data['url']
                        : $project->url;

                $project->update($data);

                $project
                    ->contacts()
                    ->sync(
                        $request->validated(
                            'contact_ids',
                            []
                        )
                    );

                if (
                    User::hasProjectUserAccessTypeColumn()
                ) {
                    $project
                        ->coworkers()
                        ->sync(
                            $this->syncCoworkerMap(
                                $nextCoworkerIds
                            )
                        );
                } else {
                    $project
                        ->coworkers()
                        ->sync(
                            $nextCoworkerIds
                        );
                }

                if (
                    $project->portal_status !==
                    'archived'
                ) {
                    $project->update([
                        'archived_at' => null,
                    ]);
                }
            }
        );

        if (! empty($addedContactIds)) {
            ClientContact::query()
                ->whereIn(
                    'id',
                    $addedContactIds
                )
                ->update([
                    'active' => true,
                    'can_access_portal' => true,
                    'access_revoked_at' => null,
                ]);
        }

        DB::afterCommit(
            function () use (
                $project,
                $addedContactIds,
                $addedCoworkerIds
            ) {
                if (! empty($addedContactIds)) {
                    $contacts =
                        ClientContact::query()
                            ->whereIn(
                                'id',
                                $addedContactIds
                            )
                            ->get();

                    foreach ($contacts as $contact) {
                        $contact->notify(
                            new ProjectInvitationNotification(
                                $project,
                                route('client.login')
                            )
                        );
                    }
                }

                if (! empty($addedCoworkerIds)) {
                    $coworkers =
                        User::query()
                            ->whereIn(
                                'id',
                                $addedCoworkerIds
                            )
                            ->where(
                                'is_admin',
                                false
                            );

                    if (
                        User::hasRoleColumn()
                    ) {
                        $coworkers->where(
                            'role',
                            'coworker'
                        );
                    }

                    foreach (
                        $coworkers->get()
                        as $coworker
                    ) {
                        $coworker->notify(
                            new ProjectInvitationNotification(
                                $project,
                                route('login')
                            )
                        );
                    }
                }
            }
        );

        return new ProjectResource(
            $project
                ->fresh()
                ->load([
                    'company',
                    'serviceProduct',
                    'contacts',
                    'coworkers',
                ])
                ->loadCount('contacts')
        );
    }

    public function archive(
        Project $project
    ): Response {
        abort_unless(
            request()->user()?->is_admin,
            403
        );

        $this->authorizeProjectAccess(
            request(),
            $project
        );

        $project->update([
            'portal_status' => 'archived',
            'archived_at' => now(),
        ]);

        return response()->noContent();
    }

    public function destroy(
        Project $project
    ): Response {
        abort_unless(
            request()->user()?->is_admin,
            403
        );

        $this->authorizeProjectAccess(
            request(),
            $project
        );

        DB::transaction(
            function () use ($project) {
                DB::table('project_documents')
                    ->where(
                        'project_id',
                        $project->id
                    )
                    ->delete();

                $project->files()->delete();

                $project->contacts()->detach();

                $project->coworkers()->detach();

                $project->delete();
            }
        );

        return response()->noContent();
    }

    public function publish(
        Project $project,
        Request $request
    ): ProjectResource {
        abort_unless(
            $request->user()?->is_admin,
            403
        );

        $this->authorizeProjectAccess(
            $request,
            $project
        );

        $data = $request->validate([
            'is_published' => [
                'required',
                'boolean',
            ],
        ]);

        $project->update($data);

        return new ProjectResource(
            $project
                ->fresh()
                ->load([
                    'company',
                    'serviceProduct',
                ])
        );
    }

    private function syncCoworkerMap(
        array $userIds
    ): array {
        $map = [];

        foreach ($userIds as $userId) {
            $map[(int) $userId] = [
                'access_type' => 'coworker',
            ];
        }

        return $map;
    }

    private function authorizeProjectAccess(
        Request $request,
        Project $project
    ): void {
        $user = $request->user();

        if ($user?->is_admin) {
            return;
        }

        abort_unless(
            $project
                ->members()
                ->whereKey($user?->id)
                ->exists(),
            403
        );
    }
}
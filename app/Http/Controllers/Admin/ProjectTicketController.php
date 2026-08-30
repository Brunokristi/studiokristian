<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientContact;
use App\Models\Project;
use App\Models\ProjectTicket;
use App\Models\TicketTag;
use App\Models\User;
use App\Notifications\NewCoworkerTicketNotification;
use App\Notifications\TicketAssignedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTicketController extends Controller
{
    public function index(
        Project $project,
        Request $request
    ): JsonResponse {
        $this->authorizeProject(
            $project,
            $request
        );

        $data = $request->validate([
            'status' => [
                'nullable',
                'in:new,in_progress,finished',
            ],

            'assignee' => [
                'nullable',
                'string',
                'regex:/^(user|contact):[0-9]+$/',
            ],

            'priority' => [
                'nullable',
                'in:low,normal,high,urgent',
            ],

            'deadline' => [
                'nullable',
                'in:nearest,furthest',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:50',
            ],
        ]);

        $query = $project
            ->tickets()
            ->with([
                'creator:id,name',
                'clientCreator:id,first_name,last_name',
                'assignee:id,name',
                'tags:id,name,color',
            ]);

        if (! empty($data['status'])) {
            $query->where(
                'status',
                $data['status']
            );
        }

        if (! empty($data['priority'])) {
            $query->where(
                'priority',
                $data['priority']
            );
        }

        if (! empty($data['assignee'])) {
            [
                $assigneeType,
                $assigneeId
            ] = explode(
                ':',
                $data['assignee']
            );

            $assigneeId = (int) $assigneeId;

            $query->where(
                function ($query) use (
                    $assigneeType,
                    $assigneeId
                ): void {
                    $query->whereJsonContains(
                        'assignees',
                        [
                            'type' => $assigneeType,
                            'id' => $assigneeId,
                        ]
                    );

                    if ($assigneeType === 'user') {
                        $query->orWhere(
                            'assigned_to',
                            $assigneeId
                        );
                    }
                }
            );
        }

        $direction =
            ($data['deadline'] ?? 'nearest') ===
            'furthest'
                ? 'desc'
                : 'asc';

        /*
         * PostgreSQL: deadlines with no value are
         * always placed after tickets that have a deadline.
         */
        $query
            ->orderByRaw(
                'deadline IS NULL ASC'
            )
            ->orderBy(
                'deadline',
                $direction
            )
            ->orderByDesc(
                'updated_at'
            );

        $perPage =
            (int) (
                $data['per_page'] ??
                20
            );

        return response()->json(
            $query->paginate(
                $perPage
            )
        );
    }

    public function store(
        Project $project,
        Request $request
    ): JsonResponse {
        $this->authorizeProject(
            $project,
            $request
        );

        $data = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'required',
                'string',
                'max:10000',
            ],

            'priority' => [
                'required',
                'in:low,normal,high,urgent',
            ],

            'deadline' => [
                'nullable',
                'date',
            ],

            'assignees' => [
                'nullable',
                'array',
            ],

            'assignees.*.type' => [
                'required',
                'in:user,contact',
            ],

            'assignees.*.id' => [
                'required',
                'integer',
            ],

            'tag_ids' => [
                'nullable',
                'array',
            ],

            'tag_ids.*' => [
                'integer',
                'exists:ticket_tags,id',
            ],
        ]);

        $data['assignees'] =
            $this->normalizeAssignees(
                $project,
                $data['assignees'] ?? []
            );

        $data['assigned_to'] =
            collect($data['assignees'])
                ->firstWhere(
                    'type',
                    'user'
                )['id'] ?? null;

        $tagIds =
            $data['tag_ids'] ?? [];

        unset(
            $data['tag_ids']
        );

        $ticket =
            $project->tickets()->create(
                $data + [
                    'created_by_user_id' =>
                        $request->user()->id,
                ]
            );

        $ticket->tags()->sync(
            $tagIds
        );

        if (
            ! $request->user()->is_admin
        ) {
            User::query()
                ->where(
                    'is_admin',
                    true
                )
                ->get()
                ->each(
                    fn (
                        User $admin
                    ) =>
                        $admin->notify(
                            new NewCoworkerTicketNotification(
                                $ticket->load('project')
                            )
                        )
                );
        }

        $this->notifyAssigneeIfCoworker(
            $project,
            $ticket,
            null
        );

        return response()->json(
            $ticket->fresh([
                'creator:id,name',
                'clientCreator:id,first_name,last_name',
                'assignee:id,name',
                'tags:id,name,color',
            ]),
            201
        );
    }

    public function update(
        Project $project,
        ProjectTicket $ticket,
        Request $request
    ): JsonResponse {
        $this->authorizeProject(
            $project,
            $request
        );

        abort_unless(
            (int) $ticket->project_id ===
            (int) $project->id,
            404
        );

        $data = $request->validate([
            'title' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'string',
                'max:10000',
            ],

            'status' => [
                'required',
                'in:new,in_progress,finished',
            ],

            'priority' => [
                'sometimes',
                'in:low,normal,high,urgent',
            ],

            'deadline' => [
                'nullable',
                'date',
            ],

            'assignees' => [
                'sometimes',
                'array',
            ],

            'assignees.*.type' => [
                'required',
                'in:user,contact',
            ],

            'assignees.*.id' => [
                'required',
                'integer',
            ],

            'tag_ids' => [
                'sometimes',
                'array',
            ],

            'tag_ids.*' => [
                'integer',
                'exists:ticket_tags,id',
            ],
        ]);

        if (
            array_key_exists(
                'assignees',
                $data
            )
        ) {
            $data['assignees'] =
                $this->normalizeAssignees(
                    $project,
                    $data['assignees']
                );

            $data['assigned_to'] =
                collect($data['assignees'])
                    ->firstWhere(
                        'type',
                        'user'
                    )['id'] ?? null;
        }

        $tagIds = null;

        if (
            array_key_exists(
                'tag_ids',
                $data
            )
        ) {
            $tagIds =
                $data['tag_ids'];

            unset(
                $data['tag_ids']
            );
        }

        $previousAssignedToValue =
            $ticket->assigned_to;

        $previousAssignedTo =
            $previousAssignedToValue !== null
                ? (int) $previousAssignedToValue
                : null;

        if (
            array_key_exists(
                'status',
                $data
            )
        ) {
            $data['finished_at'] =
                $data['status'] === 'finished'
                    ? (
                        $ticket->finished_at ??
                        now()
                    )
                    : null;
        }

        $ticket->update(
            $data
        );

        if (
            $tagIds !== null
        ) {
            $ticket->tags()->sync(
                $tagIds
            );
        }

        $this->notifyAssigneeIfCoworker(
            $project,
            $ticket,
            $previousAssignedTo
        );

        return response()->json(
            $ticket->fresh([
                'creator:id,name',
                'clientCreator:id,first_name,last_name',
                'assignee:id,name',
                'tags:id,name,color',
            ])
        );
    }

    public function destroy(
        Project $project,
        ProjectTicket $ticket,
        Request $request
    ): JsonResponse {
        $this->authorizeProject(
            $project,
            $request
        );

        abort_unless(
            (int) $ticket->project_id ===
            (int) $project->id,
            404
        );

        $ticket->tags()->detach();

        $ticket->delete();

        return response()->json(
            [],
            204
        );
    }

    public function tags(
        Request $request
    ): JsonResponse {
        abort_unless(
            $request->user(),
            401
        );

        $search =
            trim(
                (string) $request->query(
                    'search',
                    ''
                )
            );

        $query =
            TicketTag::query()
                ->select([
                    'id',
                    'name',
                    'color',
                ])
                ->orderBy(
                    'name'
                );

        if ($search !== '') {
            $query->where(
                'name',
                'ILIKE',
                '%' . $search . '%'
            );
        }

        return response()->json(
            $query->get()
        );
    }

    public function storeTag(
        Request $request
    ): JsonResponse {
        abort_unless(
            $request->user(),
            401
        );

        $data =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:100',
                ],
            ]);

        $name =
            trim(
                $data['name']
            );

        $tag =
            TicketTag::query()
                ->whereRaw(
                    'LOWER(name) = ?',
                    [
                        mb_strtolower(
                            $name
                        ),
                    ]
                )
                ->first();

        if (
            ! $tag
        ) {
            $tag =
                TicketTag::create([
                    'name' =>
                        $name,

                    'color' =>
                        'accent',
                ]);
        }

        return response()->json(
            $tag,
            $tag->wasRecentlyCreated
                ? 201
                : 200
        );
    }

    private function authorizeProject(
        Project $project,
        Request $request
    ): void {
        abort_unless(
            $request->user()->is_admin ||
            $project
                ->members()
                ->whereKey(
                    $request->user()->id
                )
                ->exists(),
            403
        );
    }

    private function normalizeAssignees(
        Project $project,
        array $assignees
    ): array {
        return collect(
            $assignees
        )
            ->map(
                fn (
                    array $assignee
                ): array => [
                    'type' =>
                        $assignee['type'],

                    'id' =>
                        (int) $assignee['id'],
                ]
            )
            ->unique(
                fn (
                    array $assignee
                ): string =>
                    $assignee['type'] .
                    ':' .
                    $assignee['id']
            )
            ->filter(
                function (
                    array $assignee
                ) use (
                    $project
                ): bool {
                    if (
                        $assignee['type'] ===
                        'user'
                    ) {
                        $isCoworker =
                            $project
                                ->coworkers()
                                ->whereKey(
                                    $assignee['id']
                                )
                                ->exists();

                        $isAdmin =
                            User::query()
                                ->whereKey(
                                    $assignee['id']
                                )
                                ->where(
                                    'is_admin',
                                    true
                                )
                                ->exists();

                        abort_unless(
                            $isCoworker ||
                            $isAdmin,
                            422,
                            'Assignee must belong to this project or be an admin.'
                        );
                    } else {
                        abort_unless(
                            ClientContact::query()
                                ->whereKey(
                                    $assignee['id']
                                )
                                ->where(
                                    'company_id',
                                    $project->company_id
                                )
                                ->exists(),
                            422,
                            'Contact must belong to this project client.'
                        );
                    }

                    return true;
                }
            )
            ->values()
            ->all();
    }

    private function notifyAssigneeIfCoworker(
        Project $project,
        ProjectTicket $ticket,
        ?int $previousAssignedTo
    ): void {
        $assignedToValue =
            $ticket->assigned_to;

        $assignedTo =
            $assignedToValue !== null
                ? (int) $assignedToValue
                : null;

        if (
            ! $assignedTo ||
            $assignedTo ===
                $previousAssignedTo
        ) {
            return;
        }

        if (
            ! $project
                ->coworkers()
                ->whereKey(
                    $assignedTo
                )
                ->exists()
        ) {
            return;
        }

        $assignee =
            User::query()
                ->whereKey(
                    $assignedTo
                )
                ->where(
                    'is_admin',
                    false
                )
                ->first();

        if (
            $assignee &&
            User::hasRoleColumn() &&
            $assignee->role !==
                'coworker'
        ) {
            return;
        }

        if (
            ! $assignee
        ) {
            return;
        }

        $assignee->notify(
            new TicketAssignedNotification(
                $ticket->fresh(
                    'project'
                )
            )
        );
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTicket;
use App\Models\ClientContact;
use App\Models\User;
use App\Notifications\NewCoworkerTicketNotification;
use App\Notifications\TicketAssignedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTicketController extends Controller
{
    public function index(Project $project, Request $request): JsonResponse
    {
        $this->authorizeProject($project, $request);
        return response()->json($project->tickets()->with(['creator:id,name', 'clientCreator:id,first_name,last_name', 'assignee:id,name'])->latest()->get());
    }
    public function store(Project $project, Request $request): JsonResponse
    {
        $this->authorizeProject($project, $request);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:10000'], 'priority' => ['required', 'in:low,normal,high,urgent'], 'assignees' => ['nullable', 'array'], 'assignees.*.type' => ['required', 'in:user,contact'], 'assignees.*.id' => ['required', 'integer']]);
        $data['assignees'] = $this->normalizeAssignees($project, $data['assignees'] ?? []);
        $data['assigned_to'] = collect($data['assignees'])->firstWhere('type', 'user')['id'] ?? null;
        $ticket = $project->tickets()->create($data + ['created_by_user_id' => $request->user()->id]);
        if (! $request->user()->is_admin) {
            User::query()
                ->where('is_admin', true)
                ->get()
                ->each(fn (User $admin) => $admin->notify(new NewCoworkerTicketNotification($ticket->load('project'))));
        }
        $this->notifyAssigneeIfCoworker($project, $ticket, null);
        return response()->json($ticket, 201);
    }
    public function update(Project $project, ProjectTicket $ticket, Request $request): JsonResponse
    {
        $this->authorizeProject($project, $request);
        abort_unless((int) $ticket->getAttribute('project_id') === (int) $project->getKey(), 404);
        $data = $request->validate(['title' => ['sometimes', 'string', 'max:255'], 'description' => ['sometimes', 'string', 'max:10000'], 'status' => ['required', 'in:new,in_progress,finished'], 'priority' => ['sometimes', 'in:low,normal,high,urgent'], 'assignees' => ['sometimes', 'array'], 'assignees.*.type' => ['required', 'in:user,contact'], 'assignees.*.id' => ['required', 'integer']]);
        if (array_key_exists('assignees', $data)) {
            $data['assignees'] = $this->normalizeAssignees($project, $data['assignees']);
            $data['assigned_to'] = collect($data['assignees'])->firstWhere('type', 'user')['id'] ?? null;
        }
        $previousAssignedToValue = $ticket->getAttribute('assigned_to');
        $previousAssignedTo = $previousAssignedToValue !== null ? (int) $previousAssignedToValue : null;
        $ticket->update($data + ['finished_at' => $data['status'] === 'finished' ? now() : null]);
        $this->notifyAssigneeIfCoworker($project, $ticket, $previousAssignedTo);
        return response()->json($ticket->fresh(['creator:id,name', 'clientCreator:id,first_name,last_name', 'assignee:id,name']));
    }

    public function destroy(Project $project, ProjectTicket $ticket, Request $request): JsonResponse
    {
        $this->authorizeProject($project, $request);
        abort_unless((int) $ticket->getAttribute('project_id') === (int) $project->getKey(), 404);

        $ticket->delete();

        return response()->json([], 204);
    }

    private function authorizeProject(Project $project, Request $request): void
    {
        abort_unless($request->user()->is_admin || $project->members()->whereKey($request->user()->id)->exists(), 403);
    }

    private function assertAssignable(Project $project, mixed $assignedTo): void
    {
        if (! $assignedTo) {
            return;
        }

        $userId = (int) $assignedTo;
        $isCoworker = $project->coworkers()->whereKey($userId)->exists();
        $isAdmin = User::query()->whereKey($userId)->where('is_admin', true)->exists();

        abort_unless($isCoworker || $isAdmin, 422, 'Assignee must belong to this project or be an admin.');
    }

    private function normalizeAssignees(Project $project, array $assignees): array
    {
        return collect($assignees)
            ->map(fn (array $assignee): array => [
                'type' => $assignee['type'],
                'id' => (int) $assignee['id'],
            ])
            ->unique(fn (array $assignee): string => $assignee['type'].':'.$assignee['id'])
            ->filter(function (array $assignee) use ($project): bool {
                if ($assignee['type'] === 'user') {
                    $isCoworker = $project->coworkers()->whereKey($assignee['id'])->exists();
                    $isAdmin = User::query()->whereKey($assignee['id'])->where('is_admin', true)->exists();
                    abort_unless($isCoworker || $isAdmin, 422, 'Assignee must belong to this project or be an admin.');
                } else {
                    abort_unless(ClientContact::query()->whereKey($assignee['id'])->where('company_id', $project->getAttribute('company_id'))->exists(), 422, 'Contact must belong to this project client.');
                }

                return true;
            })
            ->values()
            ->all();
    }

    private function notifyAssigneeIfCoworker(Project $project, ProjectTicket $ticket, ?int $previousAssignedTo): void
    {
        $assignedToValue = $ticket->getAttribute('assigned_to');
        $assignedTo = $assignedToValue !== null ? (int) $assignedToValue : null;

        if (! $assignedTo || $assignedTo === $previousAssignedTo) {
            return;
        }

        if (! $project->coworkers()->whereKey($assignedTo)->exists()) {
            return;
        }

        $assignee = User::query()
            ->whereKey($assignedTo)
            ->where('is_admin', false)
            ->first();

        if ($assignee && User::hasRoleColumn() && $assignee->role !== 'coworker') {
            return;
        }

        if (! $assignee) {
            return;
        }

        $assignee->notify(new TicketAssignedNotification($ticket->fresh('project')));
    }
}
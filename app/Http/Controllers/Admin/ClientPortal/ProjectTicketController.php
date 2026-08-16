<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTicket;
use App\Models\User;
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
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:10000'], 'priority' => ['required', 'in:low,normal,high,urgent'], 'assigned_to' => ['nullable', 'integer']]);
        $this->assertAssignable($project, $data['assigned_to'] ?? null);
        $ticket = $project->tickets()->create($data + ['created_by_user_id' => $request->user()->id]);
        $this->notifyAssigneeIfCoworker($project, $ticket, null);
        return response()->json($ticket, 201);
    }
    public function update(Project $project, ProjectTicket $ticket, Request $request): JsonResponse
    {
        $this->authorizeProject($project, $request); abort_unless($ticket->project_id === $project->id, 404);
        $data = $request->validate(['status' => ['required', 'in:new,in_progress,finished'], 'priority' => ['sometimes', 'in:low,normal,high,urgent'], 'assigned_to' => ['nullable', 'integer']]);
        $this->assertAssignable($project, $data['assigned_to'] ?? null);
        $previousAssignedTo = $ticket->assigned_to ? (int) $ticket->assigned_to : null;
        $ticket->update($data + ['finished_at' => $data['status'] === 'finished' ? now() : null]);
        $this->notifyAssigneeIfCoworker($project, $ticket, $previousAssignedTo);
        return response()->json($ticket->fresh(['creator:id,name', 'clientCreator:id,first_name,last_name', 'assignee:id,name']));
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

    private function notifyAssigneeIfCoworker(Project $project, ProjectTicket $ticket, ?int $previousAssignedTo): void
    {
        $assignedTo = $ticket->assigned_to ? (int) $ticket->assigned_to : null;

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
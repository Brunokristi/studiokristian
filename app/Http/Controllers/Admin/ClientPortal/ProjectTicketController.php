<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTicket;
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
        if (($data['assigned_to'] ?? null) && ! $project->coworkers()->whereKey($data['assigned_to'])->exists() && (int) $data['assigned_to'] !== (int) $request->user()->id) abort(422, 'Assignee must belong to this project.');
        return response()->json($project->tickets()->create($data + ['created_by_user_id' => $request->user()->id]), 201);
    }
    public function update(Project $project, ProjectTicket $ticket, Request $request): JsonResponse
    {
        $this->authorizeProject($project, $request); abort_unless($ticket->project_id === $project->id, 404);
        $data = $request->validate(['status' => ['required', 'in:new,in_progress,finished'], 'priority' => ['sometimes', 'in:low,normal,high,urgent'], 'assigned_to' => ['nullable', 'integer']]);
        $ticket->update($data + ['finished_at' => $data['status'] === 'finished' ? now() : null]);
        return response()->json($ticket->fresh(['creator:id,name', 'clientCreator:id,first_name,last_name', 'assignee:id,name']));
    }
    private function authorizeProject(Project $project, Request $request): void
    {
        abort_unless($request->user()->is_admin || $project->coworkers()->whereKey($request->user()->id)->exists(), 403);
    }
}
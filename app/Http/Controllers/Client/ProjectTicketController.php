<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Notifications\NewClientTicketNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ProjectTicketController extends Controller
{
    public function store(Project $project, Request $request): RedirectResponse
    {
        abort_unless($request->user('client')->projects()->whereKey($project->id)->exists(), 403);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:10000'], 'priority' => ['required', 'in:low,normal,high,urgent']]);
        $ticket = $project->tickets()->create($data + ['created_by_client_contact_id' => $request->user('client')->id]);
        $recipients = User::query()->where('is_admin', true)->get()->merge($project->coworkers);
        Notification::send($recipients->unique('id'), new NewClientTicketNotification($ticket->load('project')));
        return back()->with('status', 'Your support ticket was created.');
    }
}
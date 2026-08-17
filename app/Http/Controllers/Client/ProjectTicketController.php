<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Notifications\NewClientTicketNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ProjectTicketController extends Controller
{
    public function store(Project $project, Request $request): RedirectResponse
    {
        abort_unless($request->user('client')->projects()->whereKey($project->id)->exists(), 403);
        $data = $request->validate([
            'description' => ['required', 'string', 'max:10000'],
        ]);

        $title = 'Client request: '.Str::limit(trim($data['description']), 70, '...');

        $ticket = $project->tickets()->create([
            'title' => $title,
            'description' => $data['description'],
            'priority' => 'normal',
            'created_by_client_contact_id' => $request->user('client')->id,
        ]);

        $recipients = User::query()->where('is_admin', true)->get()->merge($project->coworkers);
        Notification::send($recipients->unique('id'), new NewClientTicketNotification($ticket->load('project')));
        return back()->with('status', 'Your support ticket was created.');
    }
}
<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Notifications\NewClientTicketNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ProjectTicketController extends Controller
{
    public function store(Project $project, Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user('client')->projects()->whereKey($project->id)->exists(), 403);
        $data = $request->validate([
            'description' => ['required', 'string', 'max:10000'],
        ]);

        $title = $this->buildTicketTitle($data['description']);

        $ticket = $project->tickets()->create([
            'title' => $title,
            'description' => $data['description'],
            'status' => 'new',
            'priority' => 'normal',
            'created_by_client_contact_id' => $request->user('client')->id,
        ]);

        $recipients = User::query()->where('is_admin', true)->get()->merge($project->coworkers);
        Notification::send($recipients->unique('id'), new NewClientTicketNotification($ticket->load('project')));

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Your support ticket was created.',
                'ticket' => [
                    'id' => $ticket->id,
                    'title' => $ticket->title,
                    'description' => $ticket->description,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                ],
            ], 201);
        }

        return back()->with('status', 'Your support ticket was created.');
    }

    private function buildTicketTitle(string $description): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $description) ?? '');

        if ($normalized === '') {
            return 'Client request';
        }

        $sentence = preg_split('/(?<=[.!?])\s+/u', $normalized)[0] ?? $normalized;
        $clean = trim($sentence, " \t\n\r\0\x0B-–—:;,.!?\"");

        if ($clean === '') {
            $clean = $normalized;
        }

        $smart = Str::limit($clean, 90, '...');

        return 'Client request: '.$smart;
    }
}
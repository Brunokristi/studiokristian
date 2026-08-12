<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffWorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $projects = $request->user()->projects()->with(['tickets.assignee', 'files'])->orderBy('name')->get();
        return view('staff.workspace', compact('projects'));
    }

    public function storeTicket(Project $project, Request $request): RedirectResponse
    {
        $this->authorizeProject($project, $request);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:10000'], 'priority' => ['required', 'in:low,normal,high,urgent']]);
        $project->tickets()->create($data + ['created_by_user_id' => $request->user()->id]);
        return back()->with('status', 'Ticket created.');
    }

    public function updateTicket(Project $project, ProjectTicket $ticket, Request $request): RedirectResponse
    {
        $this->authorizeProject($project, $request); abort_unless($ticket->project_id === $project->id, 404);
        $data = $request->validate(['status' => ['required', 'in:new,in_progress,finished']]);
        $ticket->update($data + ['finished_at' => $data['status'] === 'finished' ? now() : null]);
        return back();
    }

    public function file(Project $project, ProjectFile $file, Request $request): StreamedResponse
    {
        $this->authorizeProject($project, $request); abort_unless($file->project_id === $project->id, 404);
        abort_unless(Storage::disk('local')->exists($file->storage_path), 404);
        $previewable = str_starts_with($file->mime_type, 'image/') || in_array($file->mime_type, ['application/pdf', 'text/plain'], true);
        return $previewable
            ? Storage::disk('local')->response($file->storage_path, $file->original_filename, ['Content-Type' => $file->mime_type, 'Content-Disposition' => 'inline', 'X-Content-Type-Options' => 'nosniff'])
            : Storage::disk('local')->download($file->storage_path, $file->original_filename);
    }

    private function authorizeProject(Project $project, Request $request): void
    {
        abort_unless($project->coworkers()->whereKey($request->user()->id)->exists(), 403);
    }
}
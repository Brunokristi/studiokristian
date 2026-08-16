<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectFolder;
use App\Models\ProjectFolderSignature;
use App\Models\ProjectTicket;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalController extends Controller
{
    public function index(): View
    {
        return view('apps.staff');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->portalRole(),
        ]);
    }

    public function projects(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Project::query()
            ->with('company:id,name')
            ->whereNotNull('company_id')
            ->whereNull('archived_at')
            ->orderBy('updated_at', 'desc');

        if ($user->portalRole() !== 'admin') {
            $query->whereHas('members', fn ($members) => $members->whereKey($user->id));
        }

        $projects = $query->get();

        return response()->json($projects->map(function (Project $project) use ($user) {
            $permissions = $this->projectPermissions($user, $project);
            $signature = $this->projectSignatureSummary($project, $user->id, $user->portalRole());

            return [
                'id' => $project->id,
                'name' => $project->name,
                'project_code' => $project->project_code,
                'status' => $project->portal_status,
                'company' => [
                    'id' => $project->company?->id,
                    'name' => $project->company?->name,
                ],
                'permissions' => $permissions,
                'signature' => $signature,
                'open_url' => route('portal.api.projects.show', $project),
            ];
        })->values());
    }

    public function showProject(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $user = $request->user();
        $isClient = $user->portalRole() === 'client';

        $project->load([
            'company:id,name',
            'files' => function ($query) use ($isClient) {
                if ($isClient) {
                    $query->where('visibility', 'client');
                }

                $query->latest();
            },
            'tickets' => function ($query) use ($isClient, $user) {
                if ($isClient) {
                    $query->where('created_by_user_id', $user->id);
                }

                $query->latest();
            },
            'tickets.creator:id,name',
            'tickets.assignee:id,name',
            'folders' => function ($query) {
                $query
                    ->where('type', 'file')
                    ->where('resource_type', 'document')
                    ->orderBy('sort_order')
                    ->orderBy('name');
            },
        ]);

        $signatures = ProjectFolderSignature::query()
            ->where('project_id', $project->id)
            ->get()
            ->groupBy('project_folder_id');

        $documents = $project->folders
            ->filter(fn (ProjectFolder $folder) => ! $isClient || $folder->isEffectivelyClientVisible())
            ->map(function (ProjectFolder $folder) use ($request, $signatures, $isClient) {
                $mine = $signatures->get($folder->id)?->firstWhere('user_id', $request->user()->id);

                return [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'requires_signature' => (bool) $folder->requires_client_signature,
                    'client_visible' => (bool) $folder->client_visible,
                    'signed_at' => $mine?->signed_at?->toIso8601String(),
                    'can_sign' => $request->user()->can('sign', $folder),
                    'content' => $folder->content,
                ];
            })
            ->values();

        $files = $project->files
            ->filter(fn (ProjectFile $file) => $request->user()->can('view', $file))
            ->map(fn (ProjectFile $file) => [
                'id' => $file->id,
                'display_name' => $file->display_name,
                'original_filename' => $file->original_filename,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'visibility' => $file->visibility,
                'open_url' => route('portal.projects.files.open', ['project' => $project, 'file' => $file]),
                'download_url' => route('portal.projects.files.download', ['project' => $project, 'file' => $file]),
            ])
            ->values();

        return response()->json([
            'id' => $project->id,
            'name' => $project->name,
            'project_code' => $project->project_code,
            'status' => $project->portal_status,
            'company' => [
                'id' => $project->company?->id,
                'name' => $project->company?->name,
            ],
            'permissions' => $this->projectPermissions($user, $project),
            'signature' => $this->projectSignatureSummary($project, $user->id, $user->portalRole()),
            'files' => $files,
            'documents' => $documents,
            'tickets' => $project->tickets,
        ]);
    }

    public function openFile(Request $request, Project $project, ProjectFile $file, AuditLogger $audit): StreamedResponse
    {
        $this->authorize('view', $project);
        abort_unless($file->project_id === $project->id, 404);
        $this->authorize('view', $file);

        $disk = $file->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($file->storage_path), 404);

        $audit->record('portal.file.opened', $request->user(), $file, $file->project->company_id, $file->project_id, request: $request);

        return Storage::disk($disk)->response($file->storage_path, $file->original_filename, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => $this->shouldInlineForOpen($file) ? 'inline' : 'attachment',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadFile(Request $request, Project $project, ProjectFile $file, AuditLogger $audit): StreamedResponse
    {
        $this->authorize('view', $project);
        abort_unless($file->project_id === $project->id, 404);
        $this->authorize('download', $file);

        $disk = $file->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($file->storage_path), 404);

        $audit->record('portal.file.downloaded', $request->user(), $file, $file->project->company_id, $file->project_id, request: $request);

        return Storage::disk($disk)->download($file->storage_path, $file->original_filename, [
            'Content-Type' => $file->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function storeTicket(Request $request, Project $project): JsonResponse
    {
        $this->authorize('createTicket', $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
        ]);

        $ticket = $project->tickets()->create($data + [
            'created_by_user_id' => $request->user()->id,
            'status' => 'new',
        ]);

        return response()->json($ticket, 201);
    }

    public function updateTicket(Request $request, Project $project, ProjectTicket $ticket): JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($ticket->project_id === $project->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:new,in_progress,finished'],
            'priority' => ['sometimes', 'in:low,normal,high,urgent'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (array_key_exists('assigned_to', $data) && $data['assigned_to']) {
            $assignee = (int) $data['assigned_to'];
            $isMember = $project->members()->whereKey($assignee)->exists();
            $isAdmin = $project->members()->getModel()->newQuery()->whereKey($assignee)->where('is_admin', true)->exists();
            abort_unless($isMember || $isAdmin, 422, 'Assignee must belong to this project or be an admin.');
        }

        $ticket->update($data + [
            'finished_at' => ($data['status'] ?? $ticket->status) === 'finished' ? now() : null,
        ]);

        return response()->json($ticket->fresh(['creator:id,name', 'assignee:id,name']));
    }

    public function signDocument(Request $request, Project $project, ProjectFolder $folder): JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($folder->project_id === $project->id, 404);
        $this->authorize('sign', $folder);

        $signature = ProjectFolderSignature::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'project_folder_id' => $folder->id,
                'user_id' => $request->user()->id,
            ],
            [
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'metadata' => [
                    'role' => $request->user()->portalRole(),
                ],
            ]
        );

        return response()->json([
            'status' => 'signed',
            'signed_at' => $signature->signed_at?->toIso8601String(),
            'project_id' => $project->id,
            'document_id' => $folder->id,
        ]);
    }

    private function projectPermissions($user, Project $project): array
    {
        return [
            'view' => $user->can('view', $project),
            'download' => $user->can('view', $project),
            'upload' => $user->can('manageFiles', $project),
            'edit' => $user->can('update', $project),
            'delete' => $user->can('update', $project),
            'sign' => $user->portalRole() === 'client',
            'create_ticket' => $user->can('createTicket', $project),
        ];
    }

    private function projectSignatureSummary(Project $project, int $userId, string $role): array
    {
        if (! Schema::hasTable('project_folder_signatures')) {
            return [
                'required' => 0,
                'signed' => 0,
                'pending' => 0,
                'complete' => true,
            ];
        }

        $requiredIds = $project->folders()
            ->where('type', 'file')
            ->where('resource_type', 'document')
            ->where('requires_client_signature', true)
            ->pluck('id');

        if ($role === 'client') {
            $signed = ProjectFolderSignature::query()
                ->where('project_id', $project->id)
                ->where('user_id', $userId)
                ->whereIn('project_folder_id', $requiredIds)
                ->count();

            return [
                'required' => $requiredIds->count(),
                'signed' => $signed,
                'pending' => max(0, $requiredIds->count() - $signed),
                'complete' => $requiredIds->count() > 0 && $signed >= $requiredIds->count(),
            ];
        }

        $clientUserIds = $project->clients()->pluck('users.id');

        $signed = ProjectFolderSignature::query()
            ->where('project_id', $project->id)
            ->whereIn('project_folder_id', $requiredIds)
            ->when($clientUserIds->isNotEmpty(), fn ($query) => $query->whereIn('user_id', $clientUserIds), fn ($query) => $query->whereRaw('1 = 0'))
            ->distinct('project_folder_id')
            ->count('project_folder_id');

        return [
            'required' => $requiredIds->count(),
            'signed' => $signed,
            'pending' => max(0, $requiredIds->count() - $signed),
            'complete' => $requiredIds->count() > 0 && $signed >= $requiredIds->count(),
        ];
    }

    private function shouldInlineForOpen(ProjectFile $file): bool
    {
        $mime = strtolower((string) $file->mime_type);

        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        if (str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/')) {
            return true;
        }

        return in_array($mime, ['application/pdf', 'text/plain', 'text/csv', 'application/json', 'application/xml', 'text/xml'], true);
    }
}

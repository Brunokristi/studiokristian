<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientPortal\UploadProjectFilesRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectFolder;
use App\Services\AuditLogger;
use App\Services\ProjectUploadPathService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectFileController extends Controller
{
    public function index(Project $project, Request $request): JsonResponse
    {
        $folder = $request->integer('folder_id') ? $project->folders()->findOrFail($request->integer('folder_id')) : null;
        return response()->json([
            'folder' => $folder,
            'breadcrumbs' => $this->breadcrumbs($folder),
            'folders' => $project->folders()->where('parent_id', $folder?->id)->orderBy('sort_order')->orderBy('name')->get(),
            'files' => $project->files()->where('project_folder_id', $folder?->id)->latest()->get(),
        ]);
    }

    public function storeFolder(Project $project, Request $request): JsonResponse
    {
        $data = $request->validate(['parent_id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:150', 'not_in:.,..'], 'client_visible' => ['required', 'boolean']]);
        if (($data['parent_id'] ?? null) && ! $project->folders()->whereKey($data['parent_id'])->exists()) throw ValidationException::withMessages(['parent_id' => 'Parent folder must belong to this project.']);
        $folder = $project->folders()->create($data + ['created_by' => $request->user()->id]);
        app(AuditLogger::class)->record('project_folder_created', $request->user(), $folder, $project->company_id, $project->id);
        return response()->json($folder, 201);
    }

    public function updateFolder(Project $project, ProjectFolder $folder, Request $request): JsonResponse
    {
        abort_unless($folder->project_id === $project->id, 404);
        $data = $request->validate(['parent_id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:150', 'not_in:.,..'], 'client_visible' => ['required', 'boolean']]);
        if (($data['parent_id'] ?? null) && (! $project->folders()->whereKey($data['parent_id'])->exists() || $data['parent_id'] === $folder->id || $this->isDescendant($folder, $data['parent_id']))) throw ValidationException::withMessages(['parent_id' => 'Invalid destination folder.']);
        $folder->update($data);
        app(AuditLogger::class)->record('project_folder_moved', $request->user(), $folder, $project->company_id, $project->id);
        return response()->json($folder);
    }

    public function upload(Project $project, UploadProjectFilesRequest $request, ProjectUploadPathService $paths): JsonResponse
    {
        $base = $request->integer('folder_id') ? $project->folders()->findOrFail($request->integer('folder_id')) : null;
        if (collect($request->file('files'))->sum(fn ($file) => $file->getSize()) > 100 * 1024 * 1024) throw ValidationException::withMessages(['files' => 'Total upload size may not exceed 100 MB.']);
        try {
            $normalizedPaths = collect($request->file('files'))->map(fn ($upload, $index) => $paths->segments($request->input("relative_paths.{$index}", $upload->getClientOriginalName())))->all();
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['relative_paths' => $exception->getMessage()]);
        }
        $storedPaths = [];
        try {
            $created = DB::transaction(function () use ($project, $request, $normalizedPaths, $base, &$storedPaths) {
            $records = [];
            foreach ($request->file('files') as $index => $upload) {
                $segments = $normalizedPaths[$index];
                $filename = array_pop($segments); $parent = $base;
                foreach ($segments as $segment) {
                    $parent = $project->folders()->firstOrCreate(['parent_id' => $parent?->id, 'name' => $segment], ['client_visible' => $request->boolean('client_visible'), 'created_by' => $request->user()->id]);
                }
                $storagePath = 'client-portal/projects/'.$project->id.'/files/'.Str::uuid();
                Storage::disk('local')->putFileAs(dirname($storagePath), $upload, basename($storagePath));
                $storedPaths[] = $storagePath;
                $records[] = $project->files()->create(['project_folder_id' => $parent?->id, 'original_filename' => $filename, 'display_name' => $filename, 'storage_path' => $storagePath, 'mime_type' => $upload->getMimeType() ?: 'application/octet-stream', 'size' => $upload->getSize(), 'checksum' => hash_file('sha256', $upload->getRealPath()), 'visibility' => $request->boolean('client_visible') ? 'client' : 'internal', 'uploaded_by' => $request->user()->id]);
            }
            return $records;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
        foreach ($created as $file) app(AuditLogger::class)->record('project_file_uploaded', $request->user(), $file, $project->company_id, $project->id, ['size' => $file->size, 'mime_type' => $file->mime_type]);
        return response()->json(['data' => $created], 201);
    }

    public function download(Project $project, ProjectFile $file): StreamedResponse
    {
        abort_unless($file->project_id === $project->id, 404);
        abort_unless(Storage::disk('local')->exists($file->storage_path), 404);
        return Storage::disk('local')->download($file->storage_path, $file->original_filename, ['Content-Type' => $file->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function preview(Project $project, ProjectFile $file): StreamedResponse
    {
        abort_unless($file->project_id === $project->id, 404);
        abort_unless(Storage::disk('local')->exists($file->storage_path), 404);
        abort_unless(str_starts_with($file->mime_type, 'image/') || in_array($file->mime_type, ['application/pdf', 'text/plain'], true), 415);
        return Storage::disk('local')->response($file->storage_path, $file->original_filename, ['Content-Type' => $file->mime_type, 'Content-Disposition' => 'inline', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function breadcrumbs(?ProjectFolder $folder): array { $items=[]; while($folder){array_unshift($items,['id'=>$folder->id,'name'=>$folder->name]);$folder=$folder->parent;} return $items; }
    private function isDescendant(ProjectFolder $folder, int $candidate): bool { return $folder->children()->whereKey($candidate)->exists() || $folder->children()->get()->contains(fn ($child) => $this->isDescendant($child, $candidate)); }
}
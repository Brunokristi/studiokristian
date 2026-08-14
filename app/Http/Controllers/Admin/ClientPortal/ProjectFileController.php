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
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
            'files' => $project->files()
                ->where('project_folder_id', $folder?->id)
                ->latest()
                ->get()
                ->map(fn (ProjectFile $file) => $this->filePayload($project, $file))
                ->values(),
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
        $uploads = $this->normalizedUploads($request);

        if (count($uploads) === 0) {
            throw ValidationException::withMessages([
                'file' => 'No uploaded file payload was received. Send multipart/form-data with file or files[].',
            ]);
        }

        $maxFileBytes = 100 * 1024 * 1024;
        $maxTotalBytes = 500 * 1024 * 1024;

        if (collect($uploads)->sum(fn ($file) => (int) $file->getSize()) > $maxTotalBytes) {
            throw ValidationException::withMessages(['files' => 'Total upload size may not exceed 500 MB.']);
        }

        try {
            collect($uploads)
                ->map(fn (UploadedFile $upload, int $index) => $paths->segments($this->pathInputForUpload($request, $upload, $index)))
                ->all();
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['relative_paths' => $exception->getMessage()]);
        }

        $created = [];
        $errors = [];

        foreach ($uploads as $index => $upload) {
            $pathInput = $this->pathInputForUpload($request, $upload, $index);
            $storedPath = null;

            try {
                if (! $upload instanceof UploadedFile || ! $upload->isValid()) {
                    throw ValidationException::withMessages(['files' => 'File upload failed integrity checks.']);
                }

                if ((int) $upload->getSize() > $maxFileBytes) {
                    throw ValidationException::withMessages(['files' => 'Each file may not exceed 100 MB.']);
                }

                $segments = $paths->segments($pathInput);
                $filename = (string) array_pop($segments);
                $extension = $this->extensionFromFilename($filename);

                if ($this->isServerExecutableExtension($extension)) {
                    throw ValidationException::withMessages(['files' => 'This executable server-side file type is not allowed.']);
                }

                $disk = 'local';

                $record = DB::transaction(function () use ($project, $request, $base, $segments, $filename, $extension, $upload, $disk, &$storedPath) {
                    $parent = $base;

                    foreach ($segments as $segment) {
                        $parent = $project->folders()->firstOrCreate(
                            [
                                'parent_id' => $parent?->id,
                                'name' => $segment,
                            ],
                            [
                                'client_visible' => $request->boolean('client_visible'),
                                'created_by' => $request->user()->id,
                            ]
                        );
                    }

                    $storedPath = 'client-portal/projects/' . $project->id . '/files/' . (string) Str::uuid();
                    Storage::disk($disk)->putFileAs(dirname($storedPath), $upload, basename($storedPath));

                    $mimeType = $upload->getMimeType() ?: 'application/octet-stream';
                    $checksum = hash_file('sha256', $upload->getRealPath()) ?: hash('sha256', (string) Str::uuid());

                    return $project->files()->create([
                        'project_folder_id' => $parent?->id,
                        'original_filename' => $filename,
                        'display_name' => $filename,
                        'extension' => $extension,
                        'storage_path' => $storedPath,
                        'disk' => $disk,
                        'mime_type' => $mimeType,
                        'size' => (int) $upload->getSize(),
                        'checksum' => $checksum,
                        'visibility' => $request->boolean('client_visible') ? 'client' : 'internal',
                        'uploaded_by' => $request->user()->id,
                    ]);
                });

                $created[] = $this->filePayload($project, $record);

                app(AuditLogger::class)->record(
                    'project_file_uploaded',
                    $request->user(),
                    $record,
                    $project->company_id,
                    $project->id,
                    ['size' => $record->size, 'mime_type' => $record->mime_type]
                );
            } catch (\Throwable $exception) {
                if (isset($storedPath) && $storedPath) {
                    Storage::disk('local')->delete($storedPath);
                }

                $errors[] = [
                    'index' => $index,
                    'name' => basename($pathInput),
                    'message' => $exception instanceof ValidationException
                        ? collect($exception->errors())->flatten()->first()
                        : 'Upload failed for this file.',
                ];
            }
        }

        return response()->json(
            [
                'data' => $created,
                'errors' => $errors,
                'uploaded_count' => count($created),
                'failed_count' => count($errors),
            ],
            count($errors) > 0 ? 207 : 201
        );
    }

    public function download(Project $project, ProjectFile $file): StreamedResponse
    {
        abort_unless($file->project_id === $project->id, 404);
        $disk = $file->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($file->storage_path), 404);
        return Storage::disk($disk)->download($file->storage_path, $file->original_filename, [
            'Content-Type' => $file->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function open(Project $project, ProjectFile $file): StreamedResponse
    {
        abort_unless($file->project_id === $project->id, 404);
        $disk = $file->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($file->storage_path), 404);

        $disposition = $this->shouldInlineForOpen($file) ? 'inline' : 'attachment';

        return Storage::disk($disk)->response($file->storage_path, $file->original_filename, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            // Prevent browsers from executing active content when opened directly.
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self' blob: data:; media-src 'self' blob:; style-src 'unsafe-inline';",
        ]);
    }

    public function rename(Project $project, ProjectFile $file, Request $request): JsonResponse
    {
        abort_unless($file->project_id === $project->id, 404);

        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
        ])->validate();

        $normalized = $this->normalizedFilenameForRename($file, $data['name']);

        $file->update([
            'display_name' => $normalized,
            'original_filename' => $normalized,
        ]);

        app(AuditLogger::class)->record(
            'project_file_renamed',
            $request->user(),
            $file,
            $project->company_id,
            $project->id,
            ['name' => $normalized]
        );

        return response()->json($this->filePayload($project, $file->fresh()));
    }

    public function destroy(Project $project, ProjectFile $file, Request $request): JsonResponse
    {
        abort_unless($file->project_id === $project->id, 404);

        $disk = $file->disk ?: 'local';
        $storagePath = $file->storage_path;

        DB::transaction(function () use ($file, $request, $project) {
            $file->delete();
            app(AuditLogger::class)->record('project_file_deleted', $request->user(), $file, $project->company_id, $project->id);
        });

        Storage::disk($disk)->delete($storagePath);

        return response()->json(['status' => 'deleted']);
    }

    private function breadcrumbs(?ProjectFolder $folder): array { $items=[]; while($folder){array_unshift($items,['id'=>$folder->id,'name'=>$folder->name]);$folder=$folder->parent;} return $items; }
    private function isDescendant(ProjectFolder $folder, int $candidate): bool { return $folder->children()->whereKey($candidate)->exists() || $folder->children()->get()->contains(fn ($child) => $this->isDescendant($child, $candidate)); }

    private function normalizedUploads(UploadProjectFilesRequest $request): array
    {
        $uploads = $request->file('files');

        if ($uploads instanceof UploadedFile) {
            $uploads = [$uploads];
        }

        if (! is_array($uploads)) {
            $uploads = [];
        }

        $single = $request->file('file');

        if ($single instanceof UploadedFile) {
            array_unshift($uploads, $single);
        }

        return array_values(array_filter($uploads, fn ($value) => $value instanceof UploadedFile));
    }

    private function pathInputForUpload(Request $request, UploadedFile $upload, int $index): string
    {
        $relativeFromArray = $request->input("relative_paths.{$index}");

        if (is_string($relativeFromArray) && trim($relativeFromArray) !== '') {
            return $relativeFromArray;
        }

        $relativeSingle = $request->input('relative_path');

        if (is_string($relativeSingle) && trim($relativeSingle) !== '') {
            return $relativeSingle;
        }

        return $upload->getClientOriginalName();
    }

    private function extensionFromFilename(string $filename): ?string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return $extension !== '' ? $extension : null;
    }

    private function isServerExecutableExtension(?string $extension): bool
    {
        if (! $extension) {
            return false;
        }

        return in_array($extension, [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        ], true);
    }

    private function normalizedFilenameForRename(ProjectFile $file, string $requestedName): string
    {
        $name = trim($requestedName);

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'File name cannot be empty.']);
        }

        if (preg_match('/[\\x00-\\x1F\\x7F\\/\\\\]/u', $name) === 1) {
            throw ValidationException::withMessages(['name' => 'File name contains invalid characters.']);
        }

        $currentExtension = strtolower((string) ($file->extension ?: pathinfo($file->original_filename, PATHINFO_EXTENSION)));

        if ($currentExtension === '') {
            return $name;
        }

        $providedExtension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if (
            $providedExtension !== ''
            && $providedExtension !== $currentExtension
            && preg_match('/^[a-z]{1,8}$/', $providedExtension) === 1
        ) {
            throw ValidationException::withMessages(['name' => 'File extension cannot be changed during rename.']);
        }

        $baseName = $providedExtension === $currentExtension
            ? (string) substr($name, 0, -1 * (strlen($providedExtension) + 1))
            : $name;

        $baseName = trim($baseName);

        if ($baseName === '') {
            throw ValidationException::withMessages(['name' => 'File name cannot be empty.']);
        }

        return $baseName . '.' . $currentExtension;
    }

    private function shouldInlineForOpen(ProjectFile $file): bool
    {
        $mime = strtolower((string) $file->mime_type);
        $extension = strtolower((string) ($file->extension ?: pathinfo($file->original_filename, PATHINFO_EXTENSION)));

        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        if (str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/')) {
            return true;
        }

        if (in_array($mime, ['application/pdf', 'text/plain', 'text/csv', 'application/json', 'application/xml', 'text/xml'], true)) {
            return true;
        }

        return in_array($extension, [
            'svg', 'txt', 'md', 'json', 'xml', 'csv',
            'html', 'css', 'js', 'ts', 'vue', 'php', 'py', 'java', 'c', 'cpp', 'h', 'sql', 'sh', 'yaml', 'yml',
            'mp3', 'wav', 'ogg', 'm4a', 'mp4', 'webm', 'mov',
            'png', 'jpg', 'jpeg', 'webp', 'gif', 'pdf',
        ], true);
    }

    private function filePayload(Project $project, ProjectFile $file): array
    {
        return [
            'id' => $file->id,
            'project_id' => $file->project_id,
            'folder_id' => $file->project_folder_id,
            'display_name' => $file->display_name,
            'original_filename' => $file->original_filename,
            'extension' => $file->extension,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'disk' => $file->disk,
            'visibility' => $file->visibility,
            'created_at' => $file->created_at,
            'updated_at' => $file->updated_at,
            'open_url' => route('admin.client-portal.api.projects.files.open', [$project, $file]),
            'download_url' => route('admin.client-portal.api.projects.files.download', [$project, $file]),
        ];
    }
}
<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\CompanyStorageFolder;
use App\Services\ProjectUploadPathService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InternalStorageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('company_storage_folders')) {
            return response()->json([
                'folders' => [],
            ]);
        }

        $folders = CompanyStorageFolder::query()
            ->whereNull('company_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (CompanyStorageFolder $folder) => $this->folderPayload($folder))
            ->values();

        return response()->json([
            'folders' => $folders,
        ]);
    }

    public function updateStructure(Request $request): JsonResponse
    {
        if (! Schema::hasTable('company_storage_folders')) {
            throw ValidationException::withMessages([
                'storage' => 'Company storage is not available yet. Run database migrations first.',
            ]);
        }

        $data = $request->validate([
            'folders' => ['array'],
            'folders.*.id' => ['nullable', 'integer'],
            'folders.*.client_key' => ['required', 'string', 'max:100'],
            'folders.*.parent_client_key' => ['nullable', 'string', 'max:100'],
            'folders.*.type' => ['nullable', 'in:folder,file'],
            'folders.*.name' => ['required', 'string', 'max:150', 'not_in:.,..'],
            'folders.*.resource_type' => ['nullable', 'in:document,file,link'],
            'folders.*.requirement_level' => ['nullable', 'in:required,recommended,optional'],
            'folders.*.requires_client_signature' => ['nullable', 'boolean'],
            'folders.*.template_name' => ['nullable', 'string', 'max:255'],
            'folders.*.content' => ['nullable', 'string'],
            'folders.*.url' => ['nullable', 'string', 'max:2000'],
            'folders.*.client_visible' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $existing = CompanyStorageFolder::query()->whereNull('company_id')->get()->keyBy('id');
            $processedIds = [];
            $map = [];
            $pending = collect($data['folders'] ?? [])->values();

            while ($pending->isNotEmpty()) {
                $progress = false;

                foreach ($pending as $index => $input) {
                    $parentClientKey = $input['parent_client_key'] ?? null;
                    if ($parentClientKey && ! isset($map[$parentClientKey])) {
                        continue;
                    }

                    $folder = null;
                    if (! empty($input['id'])) {
                        $folder = CompanyStorageFolder::query()->whereNull('company_id')->whereKey((int) $input['id'])->firstOrFail();
                    }

                    if (! $folder) {
                        $folder = new CompanyStorageFolder();
                    }

                    $resourceType = ($input['type'] ?? 'folder') === 'file'
                        ? ($input['resource_type'] ?? 'document')
                        : null;

                    $isDocument = $resourceType === 'document';

                    $folder->fill([
                        'company_id' => null,
                        'parent_id' => $parentClientKey ? $map[$parentClientKey] : null,
                        'type' => $input['type'] ?? 'folder',
                        'name' => $input['name'],
                        'resource_type' => $resourceType,
                        'requirement_level' => $input['requirement_level'] ?? null,
                        'requires_client_signature' => $isDocument
                            ? (bool) ($input['requires_client_signature'] ?? false)
                            : false,
                        'template_name' => $input['template_name'] ?? null,
                        'content' => $input['content'] ?? null,
                        'url' => $resourceType === 'link' ? ($input['url'] ?? null) : null,
                        'client_visible' => false,
                        'sort_order' => $index,
                        'created_by' => $folder->created_by ?: $request->user()?->id,
                    ]);
                    $folder->save();

                    $processedIds[] = $folder->id;
                    $map[$input['client_key']] = $folder->id;
                    $pending->forget($index);
                    $progress = true;
                }

                if (! $progress) {
                    throw ValidationException::withMessages([
                        'folders' => 'Folder tree contains a missing or circular parent.',
                    ]);
                }
            }

            if (! empty($processedIds)) {
                CompanyStorageFolder::query()->whereNull('company_id')->whereNotIn('id', $processedIds)->delete();
            } else {
                CompanyStorageFolder::query()->whereNull('company_id')->delete();
            }
        });

        return response()->json([
            'folders' => CompanyStorageFolder::query()
                ->whereNull('company_id')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (CompanyStorageFolder $folder) => $this->folderPayload($folder))
                ->values(),
        ]);
    }

    public function upload(Request $request, ProjectUploadPathService $paths): JsonResponse
    {
        if (! Schema::hasTable('company_storage_folders')) {
            throw ValidationException::withMessages([
                'storage' => 'Company storage is not available yet. Run database migrations first.',
            ]);
        }

        $data = $request->validate([
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('company_storage_folders', 'id')->where(fn ($query) => $query
                    ->whereNull('company_id')
                    ->where('type', 'folder')),
            ],
            'file' => ['nullable', 'file', 'required_without:files'],
            'files' => ['nullable', 'array', 'required_without:file', 'min:1', 'max:1000'],
            'files.*' => ['file'],
            'relative_path' => ['nullable', 'string', 'max:2000'],
            'relative_paths' => ['nullable', 'array'],
            'relative_paths.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $base = isset($data['folder_id'])
            ? CompanyStorageFolder::query()->whereNull('company_id')->whereKey((int) $data['folder_id'])->first()
            : null;

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

                $record = DB::transaction(function () use ($request, $base, $segments, $filename, $extension, $upload, $disk, &$storedPath) {
                    $parent = $base;

                    foreach ($segments as $segment) {
                        $parent = CompanyStorageFolder::query()->firstOrCreate(
                            [
                                'company_id' => null,
                                'parent_id' => $parent?->id,
                                'type' => 'folder',
                                'name' => $segment,
                            ],
                            [
                                'resource_type' => null,
                                'requirement_level' => null,
                                'requires_client_signature' => false,
                                'template_name' => null,
                                'content' => null,
                                'url' => null,
                                'client_visible' => false,
                                'sort_order' => 0,
                                'created_by' => $request->user()?->id,
                            ]
                        );
                    }

                    $storedPath = 'client-portal/internal-storage/files/' . (string) Str::uuid();
                    Storage::disk($disk)->putFileAs(dirname($storedPath), $upload, basename($storedPath));

                    $sortOrder = (int) CompanyStorageFolder::query()
                        ->whereNull('company_id')
                        ->where('parent_id', $parent?->id)
                        ->max('sort_order') + 1;

                    return CompanyStorageFolder::query()->create([
                        'company_id' => null,
                        'parent_id' => $parent?->id,
                        'type' => 'file',
                        'name' => $filename,
                        'original_filename' => $filename,
                        'extension' => $extension,
                        'resource_type' => 'file',
                        'requirement_level' => null,
                        'requires_client_signature' => false,
                        'template_name' => $filename,
                        'content' => null,
                        'url' => null,
                        'disk' => $disk,
                        'storage_path' => $storedPath,
                        'mime_type' => $upload->getMimeType() ?: 'application/octet-stream',
                        'size' => (int) $upload->getSize(),
                        'checksum' => hash_file('sha256', $upload->getRealPath()) ?: null,
                        'uploaded_by' => $request->user()?->id,
                        'client_visible' => false,
                        'sort_order' => $sortOrder,
                        'created_by' => $request->user()?->id,
                    ]);
                });

                $created[] = $this->folderPayload($record);
            } catch (\Throwable $exception) {
                if ($storedPath) {
                    Storage::disk('local')->delete($storedPath);
                }

                throw $exception;
            }
        }

        return response()->json([
            'data' => $created,
            'uploaded_count' => count($created),
            'failed_count' => 0,
        ], 201);
    }

    public function open(CompanyStorageFolder $folder): StreamedResponse
    {
        $this->assertOpenableFile($folder);

        $disk = $folder->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($folder->storage_path), 404);

        return Storage::disk($disk)->response($folder->storage_path, $folder->original_filename ?: $folder->name, [
            'Content-Type' => $folder->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(CompanyStorageFolder $folder): StreamedResponse
    {
        $this->assertOpenableFile($folder);

        $disk = $folder->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($folder->storage_path), 404);

        return Storage::disk($disk)->download($folder->storage_path, $folder->original_filename ?: $folder->name, [
            'Content-Type' => $folder->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function folderPayload(CompanyStorageFolder $folder): array
    {
        $payload = $folder->toArray();

        if ($folder->type === 'file' && $folder->resource_type === 'file' && $folder->storage_path) {
            $payload['open_url'] = route('admin.client-portal.api.internal-storage.files.open', $folder);
            $payload['download_url'] = route('admin.client-portal.api.internal-storage.files.download', $folder);
        }

        return $payload;
    }

    private function assertOpenableFile(CompanyStorageFolder $folder): void
    {
        abort_unless($folder->company_id === null, 404);
        abort_unless($folder->type === 'file', 404);
        abort_unless($folder->resource_type === 'file', 404);
        abort_unless($folder->storage_path, 404);
    }

    private function normalizedUploads(Request $request): array
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
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        if (! is_string($extension) || $extension === '') {
            return null;
        }

        return strtolower($extension);
    }

    private function isServerExecutableExtension(?string $extension): bool
    {
        if (! $extension) {
            return false;
        }

        return in_array(strtolower($extension), [
            'php',
            'phtml',
            'phar',
            'php3',
            'php4',
            'php5',
            'php7',
            'php8',
            'cgi',
            'pl',
            'py',
            'rb',
            'sh',
            'bash',
            'zsh',
            'ksh',
            'exe',
            'dll',
            'so',
            'bat',
            'cmd',
            'com',
            'js',
            'mjs',
            'cjs',
            'jar',
            'war',
        ], true);
    }
}

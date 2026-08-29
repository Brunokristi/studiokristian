<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceBlueprintFolderDefinition;
use App\Models\ServiceBlueprintVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceBlueprintFileController extends Controller
{
    public function open(ServiceBlueprintFolderDefinition $folder): BinaryFileResponse
    {
        $this->assertOpenableFile($folder);

        $disk = $folder->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($folder->storage_path), 404);

        return response()->download(Storage::disk($disk)->path($folder->storage_path), $folder->original_filename ?: $folder->name, [
            'Content-Type' => $folder->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(ServiceBlueprintFolderDefinition $folder): BinaryFileResponse
    {
        $this->assertOpenableFile($folder);

        $disk = $folder->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($folder->storage_path), 404);

        return response()->download(Storage::disk($disk)->path($folder->storage_path), $folder->original_filename ?: $folder->name, [
            'Content-Type' => $folder->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function upload(ServiceBlueprintVersion $version, Request $request): JsonResponse
    {
        if ($version->status !== 'draft') {
            throw new HttpException(409, 'Published blueprint files are immutable.');
        }

        $data = $request->validate([
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('service_blueprint_folder_definitions', 'id')->where(fn ($query) => $query
                    ->where('service_blueprint_version_id', $version->id)
                    ->where('type', 'folder')),
            ],
            'file' => ['nullable', 'file', 'required_without:files'],
            'files' => ['nullable', 'array', 'required_without:file', 'min:1', 'max:1000'],
            'files.*' => ['file'],
            'relative_path' => ['nullable', 'string', 'max:2000'],
            'relative_paths' => ['nullable', 'array'],
            'relative_paths.*' => ['nullable', 'string', 'max:2000'],
            'client_visible' => ['nullable', 'boolean'],
        ]);

        $uploads = $this->normalizedUploads($request);
        if (count($uploads) === 0) {
            throw ValidationException::withMessages([
                'file' => 'No uploaded file payload was received. Send multipart/form-data with file or files[].',
            ]);
        }

        foreach ($uploads as $index => $upload) {
            $this->segmentsFromPathInput($this->pathInputForUpload($request, $upload, $index));
        }

        $parent = isset($data['folder_id']) ? $version->folders()->whereKey($data['folder_id'])->first() : null;
        $created = [];

        foreach ($uploads as $index => $upload) {
            $storedPath = null;

            try {
                if (! $upload->isValid()) {
                    throw ValidationException::withMessages(['files' => 'File upload failed integrity checks.']);
                }

                $segments = $this->segmentsFromPathInput(
                    $this->pathInputForUpload($request, $upload, $index)
                );
                $originalName = (string) array_pop($segments);
                $extension = $this->extensionFromFilename($originalName);

                if ($this->isServerExecutableExtension($extension)) {
                    throw ValidationException::withMessages(['files' => 'This executable server-side file type is not allowed.']);
                }

                $disk = 'local';
                $storedPath = 'client-portal/blueprints/' . $version->id . '/files/' . (string) Str::uuid();
                Storage::disk($disk)->putFileAs(dirname($storedPath), $upload, basename($storedPath));

                $payload = DB::transaction(function () use ($version, $request, $parent, $segments, $upload, $storedPath, $disk, $originalName, $extension) {
                    $targetParent = $parent;

                    foreach ($segments as $segment) {
                        $targetParent = $version->folders()->firstOrCreate(
                            [
                                'parent_id' => $targetParent?->id,
                                'type' => 'folder',
                                'name' => $segment,
                            ],
                            [
                                'client_visible' => $request->boolean('client_visible', true),
                            ]
                        );
                    }

                    $sortOrder = (int) $version->folders()->where('parent_id', $targetParent?->id)->max('sort_order') + 1;

                    $file = $version->folders()->create([
                        'parent_id' => $targetParent?->id,
                        'type' => 'file',
                        'name' => $originalName,
                        'original_filename' => $originalName,
                        'extension' => $extension,
                        'resource_type' => 'file',
                        'requirement_level' => null,
                        'requires_client_signature' => false,
                        'template_name' => $originalName,
                        'content' => null,
                        'url' => null,
                        'disk' => $disk,
                        'storage_path' => $storedPath,
                        'mime_type' => $upload->getMimeType() ?: 'application/octet-stream',
                        'size' => (int) $upload->getSize(),
                        'checksum' => hash_file('sha256', $upload->getRealPath()) ?: null,
                        'uploaded_by' => $request->user()?->id,
                        'client_visible' => $request->boolean('client_visible', true),
                        'sort_order' => $sortOrder,
                    ]);

                    return $this->folderPayload($file);
                });

                $created[] = $payload;
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

    private function segmentsFromPathInput(string $pathInput): array
    {
        $value = trim($pathInput);

        if ($value === '') {
            throw ValidationException::withMessages([
                'relative_paths' => 'Relative path cannot be empty.',
            ]);
        }

        $normalized = str_replace('\\', '/', $value);
        $segments = array_values(array_filter(explode('/', $normalized), fn ($segment) => trim((string) $segment) !== ''));

        if (count($segments) === 0) {
            throw ValidationException::withMessages([
                'relative_paths' => 'Relative path cannot be empty.',
            ]);
        }

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw ValidationException::withMessages([
                    'relative_paths' => 'Relative paths may not contain . or .. segments.',
                ]);
            }

            if (preg_match('/[\x00-\x1F\x7F]/u', $segment) === 1) {
                throw ValidationException::withMessages([
                    'relative_paths' => 'Relative paths contain invalid control characters.',
                ]);
            }
        }

        return $segments;
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

    private function folderPayload(ServiceBlueprintFolderDefinition $folder): array
    {
        return [
            'id' => $folder->id,
            'client_key' => (string) $folder->id,
            'parent_id' => $folder->parent_id,
            'type' => $folder->type,
            'name' => $folder->name,
            'resource_type' => $folder->resource_type,
            'requirement_level' => $folder->requirement_level,
            'requires_client_signature' => (bool) $folder->requires_client_signature,
            'template_name' => $folder->template_name,
            'content' => $folder->content,
            'url' => $folder->url,
            'client_visible' => (bool) $folder->client_visible,
            'mime_type' => $folder->mime_type,
            'extension' => $folder->extension,
            'size' => $folder->size,
            'disk' => $folder->disk,
            'storage_path' => $folder->storage_path,
            'open_url' => $folder->open_url,
            'download_url' => $folder->download_url,
        ];
    }

    private function assertOpenableFile(ServiceBlueprintFolderDefinition $folder): void
    {
        if ($folder->type !== 'file' || $folder->resource_type !== 'file' || ! $folder->storage_path) {
            throw new HttpException(422, 'Only uploaded binary blueprint files can be opened or downloaded.');
        }
    }
}

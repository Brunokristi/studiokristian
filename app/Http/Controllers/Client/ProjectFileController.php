<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Services\AuditLogger;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectFileController extends Controller
{
    public function index(
        Request $request,
        Project $project
    ): JsonResponse {
        $this->authorize(
            'view',
            $project
        );

        $folderId =
            $request->query(
                'folder_id'
            );

        $query =
            ProjectFile::query()
                ->where(
                    'project_id',
                    $project->id
                )
                ->where(
                    'visibility',
                    'client'
                );

        if (
            $folderId !== null &&
            $folderId !== ''
        ) {
            $query->where(
                'project_folder_id',
                $folderId
            );
        } else {
            $query->whereNull(
                'project_folder_id'
            );
        }

        $files =
            $query
                ->orderBy(
                    'original_filename'
                )
                ->get([
                    'id',
                    'project_id',
                    'project_folder_id',
                    'display_name',
                    'original_filename',
                    'mime_type',
                    'extension',
                    'size',
                ])
                ->map(
                    function (
                        ProjectFile $file
                    ) {
                        return [
                            'id' =>
                                $file->id,

                            'folder_id' =>
                                $file->project_folder_id,

                            'display_name' =>
                                $file->display_name,

                            'original_filename' =>
                                $file->original_filename,

                            'mime_type' =>
                                $file->mime_type,

                            'extension' =>
                                $file->extension,

                            'size' =>
                                $file->size,

                            'open_url' =>
                                route(
                                    'client.files.open',
                                    [
                                        'file' =>
                                            $file->id
                                    ]
                                ),

                            'download_url' =>
                                route(
                                    'client.files.download',
                                    [
                                        'file' =>
                                            $file->id
                                    ]
                                ),
                        ];
                    }
                )
                ->values();

        return response()->json([
            'files' =>
                $files,
        ]);
    }


    public function open(
        Request $request,
        ProjectFile $file,
        AuditLogger $audit
    ): StreamedResponse {
        $this->authorize(
            'view',
            $file
        );

        $disk =
            $file->disk ?: 'local';

        /** @var FilesystemAdapter $storage */
        $storage =
            Storage::disk(
                $disk
            );

        abort_unless(
            $storage->exists(
                $file->storage_path
            ),
            404
        );

        $audit->record(
            'file.opened',
            $request->user('client'),
            $file,
            $file->project->company_id,
            $file->project_id,
            request: $request
        );

        return $storage->response(
            $file->storage_path,
            $file->original_filename,
            [
                'Content-Type' =>
                    $file->mime_type,

                'Content-Disposition' =>
                    $this->shouldInlineForOpen(
                        $file
                    )
                        ? 'inline'
                        : 'attachment',

                'Cache-Control' =>
                    'private, no-store',

                'X-Content-Type-Options' =>
                    'nosniff',

                'Content-Security-Policy' =>
                    "sandbox; default-src 'none'; img-src 'self' blob: data:; media-src 'self' blob:; style-src 'unsafe-inline';",
            ]
        );
    }


    public function download(
        Request $request,
        ProjectFile $file,
        AuditLogger $audit
    ): StreamedResponse {
        $this->authorize(
            'view',
            $file
        );

        $disk =
            $file->disk ?: 'local';

        /** @var FilesystemAdapter $storage */
        $storage =
            Storage::disk(
                $disk
            );

        abort_unless(
            $storage->exists(
                $file->storage_path
            ),
            404
        );

        $audit->record(
            'file.downloaded',
            $request->user('client'),
            $file,
            $file->project->company_id,
            $file->project_id,
            request: $request
        );

        return $storage->download(
            $file->storage_path,
            $file->original_filename,
            [
                'Content-Type' =>
                    $file->mime_type,

                'Cache-Control' =>
                    'private, no-store',

                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }


    private function shouldInlineForOpen(
        ProjectFile $file
    ): bool {
        $mime =
            strtolower(
                (string) $file->mime_type
            );

        $extension =
            strtolower(
                (string) (
                    $file->extension
                    ?: pathinfo(
                        $file->original_filename,
                        PATHINFO_EXTENSION
                    )
                )
            );

        if (
            str_starts_with(
                $mime,
                'image/'
            )
        ) {
            return true;
        }

        if (
            str_starts_with(
                $mime,
                'audio/'
            ) ||
            str_starts_with(
                $mime,
                'video/'
            )
        ) {
            return true;
        }

        if (
            in_array(
                $mime,
                [
                    'application/pdf',
                    'text/plain',
                    'text/csv',
                    'application/json',
                    'application/xml',
                    'text/xml',
                ],
                true
            )
        ) {
            return true;
        }

        return in_array(
            $extension,
            [
                'svg',
                'txt',
                'md',
                'json',
                'xml',
                'csv',
                'html',
                'css',
                'js',
                'ts',
                'vue',
                'php',
                'py',
                'java',
                'c',
                'cpp',
                'h',
                'sql',
                'sh',
                'yaml',
                'yml',
                'mp3',
                'wav',
                'ogg',
                'm4a',
                'mp4',
                'webm',
                'mov',
                'png',
                'jpg',
                'jpeg',
                'webp',
                'gif',
                'pdf',
            ],
            true
        );
    }
}
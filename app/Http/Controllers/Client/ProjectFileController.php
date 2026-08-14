<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ProjectFile;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectFileController extends Controller
{
    public function open(Request $request, ProjectFile $file, AuditLogger $audit): StreamedResponse
    {
        $this->authorize('view', $file);
        $disk = $file->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($file->storage_path), 404);

        $audit->record('file.opened', $request->user(), $file, $file->project->company_id, $file->project_id, request: $request);

        return Storage::disk($disk)->response($file->storage_path, $file->original_filename, [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => $this->shouldInlineForOpen($file) ? 'inline' : 'attachment',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self' blob: data:; media-src 'self' blob:; style-src 'unsafe-inline';",
        ]);
    }

    public function download(Request $request, ProjectFile $file, AuditLogger $audit): StreamedResponse
    {
        $this->authorize('view', $file);
        $disk = $file->disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($file->storage_path), 404);
        $audit->record('file.downloaded', $request->user(), $file, $file->project->company_id, $file->project_id, request: $request);

        return Storage::disk($disk)->download($file->storage_path, $file->original_filename, [
            'Content-Type' => $file->mime_type, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
}
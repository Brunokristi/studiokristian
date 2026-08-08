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
    public function download(Request $request, ProjectFile $file, AuditLogger $audit): StreamedResponse
    {
        $this->authorize('view', $file);
        abort_unless(Storage::disk('local')->exists($file->storage_path), 404);
        $audit->record('file.downloaded', $request->user(), $file, $file->project->company_id, $file->project_id, request: $request);

        return Storage::disk('local')->download($file->storage_path, $file->original_filename, [
            'Content-Type' => $file->mime_type, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
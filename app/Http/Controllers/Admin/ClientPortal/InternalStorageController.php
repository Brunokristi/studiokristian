<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalStorageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projectId = $request->integer('project_id');
        $query = Project::query()->with(['files' => fn ($files) => $files->where('visibility', 'internal')->orderBy('display_name')]);

        if ($projectId) {
            $query->whereKey($projectId);
        }

        $projects = $query->orderBy('name')->get()->map(fn (Project $project) => [
            'id' => $project->id,
            'name' => $project->name,
            'project_code' => $project->project_code,
            'files' => $project->files->map(fn ($file) => [
                'id' => $file->id,
                'display_name' => $file->display_name,
                'original_filename' => $file->original_filename,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'visibility' => $file->visibility,
                'created_at' => $file->created_at?->toIso8601String(),
            ]),
        ]);

        return response()->json($projects);
    }
}

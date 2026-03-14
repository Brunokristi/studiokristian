<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = Project::query()
            ->with(['images' => fn ($query) => $query->orderBy('sort_order')])
            ->orderBy('name')
            ->get()
            ->map(function (Project $project) {
                return [
                    'name' => $project->name,
                    'url' => $project->url,
                    'summary' => $project->summary,
                    'hex_color' => $project->hex_color,
                    'logo_path' => $project->logo_path,
                    'cover_image' => optional($project->images->first())->path,
                ];
            });

        return response()->json($projects);
    }

    public function show(string $url): JsonResponse
    {
        $project = Project::query()
            ->with([
                'images' => fn ($query) => $query->orderBy('sort_order'),
                'features' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->where('url', $url)
            ->firstOrFail();

        return response()->json([
            'name' => $project->name,
            'url' => $project->url,
            'summary' => $project->summary,
            'hex_color' => $project->hex_color,
            'logo_path' => $project->logo_path,
            'images' => $project->images->map(fn ($image) => [
                'path' => $image->path,
                'description' => $image->description,
            ]),
            'features' => $project->features->map(fn ($feature) => [
                'title' => $feature->title,
                'description' => $feature->description,
                'sort_order' => $feature->sort_order,
            ]),
        ]);
    }
}

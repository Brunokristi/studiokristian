<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);

        $projects = Project::query()
            ->with(['images' => fn ($query) => $query->orderBy('sort_order')])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Project $project) use ($locale) {
                return [
                    'name' => $this->localizedValue($project->name_translations, $project->name, $locale),
                    'url' => $project->url,
                    'summary' => $this->localizedValue($project->summary_translations, $project->summary, $locale),
                    'hex_color' => $project->hex_color,
                    'logo_path' => $project->logo_path,
                    'cover_image' => optional($project->images->first())->path,
                ];
            });

        return response()->json($projects);
    }

    public function show(Request $request, string $url): JsonResponse
    {
        $locale = $this->resolveLocale($request);

        $project = Project::query()
            ->with([
                'images' => fn ($query) => $query->orderBy('sort_order'),
                'features' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->where('url', $url)
            ->firstOrFail();

        return response()->json([
            'name' => $this->localizedValue($project->name_translations, $project->name, $locale),
            'url' => $project->url,
            'summary' => $this->localizedValue($project->summary_translations, $project->summary, $locale),
            'hex_color' => $project->hex_color,
            'logo_path' => $project->logo_path,
            'images' => $project->images->map(fn ($image) => [
                'path' => $image->path,
                'description' => $this->localizedValue($image->description_translations, $image->description, $locale),
            ]),
            'features' => $project->features->map(fn ($feature) => [
                'title' => $this->localizedValue($feature->title_translations, $feature->title, $locale),
                'description' => $this->localizedValue($feature->description_translations, $feature->description, $locale),
                'sort_order' => $feature->sort_order,
            ]),
        ]);
    }

    private function resolveLocale(Request $request): string
    {
        $locale = $request->query('locale');

        if (!is_string($locale) || $locale === '') {
            return 'en';
        }

        return strtolower($locale);
    }

    private function localizedValue(?array $translations, ?string $fallback, string $locale): ?string
    {
        if (is_array($translations)) {
            if (isset($translations[$locale]) && is_string($translations[$locale]) && $translations[$locale] !== '') {
                return $translations[$locale];
            }

            if (isset($translations['en']) && is_string($translations['en']) && $translations['en'] !== '') {
                return $translations['en'];
            }
        }

        return $fallback;
    }
}

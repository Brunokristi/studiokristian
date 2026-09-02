<?php

namespace App\Http\Controllers\PublicSite;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends \App\Http\Controllers\Controller
{
    public function index(Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);

        $limit = $request->integer('limit');

        $gallery = $request->boolean('gallery');

        $query = Project::query()
            ->where('is_published', true)
            ->orderByDesc('created_at');

        /*
        |--------------------------------------------------------------------------
        | Project images
        |--------------------------------------------------------------------------
        |
        | Gallery:
        |   Load ALL images belonging to each project.
        |
        | Normal portfolio:
        |   Load only the first image as the cover image.
        |
        */

        $query->with([
            'images' => function ($query) use ($gallery) {
                $query->orderBy('sort_order');

                if (!$gallery) {
                    $query->limit(1);
                }
            },
        ]);

        /*
        |--------------------------------------------------------------------------
        | Limit projects
        |--------------------------------------------------------------------------
        */

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        /*
        |--------------------------------------------------------------------------
        | Build response
        |--------------------------------------------------------------------------
        */

        $projects = $query
            ->get()
            ->map(function (Project $project) use (
                $locale,
                $gallery
            ) {
                /*
                |--------------------------------------------------------------------------
                | Gallery response
                |--------------------------------------------------------------------------
                |
                | Used by the homepage project wall.
                |
                | Each project contains:
                | - name
                | - summary
                | - logo
                | - ALL project images
                |
                */

                if ($gallery) {
                    return [
                        'name' =>
                            $this->localizedValue(
                                $project->name_translations,
                                $project->name,
                                $locale
                            ),

                        'url' =>
                            $project->url,

                        'summary' =>
                            $this->localizedValue(
                                $project->summary_translations,
                                $project->summary,
                                $locale
                            ),

                        'logo_path' =>
                            $project->logo_path,

                        'images' =>
                            $project->images
                                ->map(
                                    fn ($image) => [
                                        'path' =>
                                            $image->path,
                                    ]
                                )
                                ->values(),
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Normal portfolio response
                |--------------------------------------------------------------------------
                */

                return [
                    'name' =>
                        $this->localizedValue(
                            $project->name_translations,
                            $project->name,
                            $locale
                        ),

                    'url' =>
                        $project->url,

                    'live_url' =>
                        $project->live_url,

                    'summary' =>
                        $this->localizedValue(
                            $project->summary_translations,
                            $project->summary,
                            $locale
                        ),

                    'hex_color' =>
                        $project->hex_color,

                    'logo_path' =>
                        $project->logo_path,

                    'cover_image' =>
                        optional(
                            $project->images->first()
                        )->path,
                ];
            })
            ->values();

        return response()->json($projects);
    }

    public function show(
        Request $request,
        string $url
    ): JsonResponse {
        $locale = $this->resolveLocale($request);

        $project = Project::query()
            ->where('is_published', true)
            ->with([
                'images' => fn ($query) =>
                    $query->orderBy('sort_order'),

                'features' => fn ($query) =>
                    $query->orderBy('sort_order'),
            ])
            ->where('url', $url)
            ->firstOrFail();

        return response()->json([
            'name' =>
                $this->localizedValue(
                    $project->name_translations,
                    $project->name,
                    $locale
                ),

            'url' =>
                $project->url,

            'live_url' =>
                $project->live_url,

            'summary' =>
                $this->localizedValue(
                    $project->summary_translations,
                    $project->summary,
                    $locale
                ),

            'hex_color' =>
                $project->hex_color,

            'logo_path' =>
                $project->logo_path,

            'podcast_path' =>
                $project->podcast_path,

            'images' =>
                $project->images->map(
                    fn ($image) => [
                        'path' =>
                            $image->path,

                        'description' =>
                            $this->localizedValue(
                                $image->description_translations,
                                $image->description,
                                $locale
                            ),

                        'alt' =>
                            $this->localizedValue(
                                $image->alt_translations,
                                $image->alt,
                                $locale
                            ),
                    ]
                ),

            'features' =>
                $project->features->map(
                    fn ($feature) => [
                        'title' =>
                            $this->localizedValue(
                                $feature->title_translations,
                                $feature->title,
                                $locale
                            ),

                        'description' =>
                            $this->localizedValue(
                                $feature->description_translations,
                                $feature->description,
                                $locale
                            ),

                        'sort_order' =>
                            $feature->sort_order,
                    ]
                ),
        ]);
    }

    private function resolveLocale(
        Request $request
    ): string {
        $locale = $request->query('locale');

        if (
            !is_string($locale) ||
            $locale === ''
        ) {
            return 'en';
        }

        return strtolower($locale);
    }

    private function localizedValue(
        ?array $translations,
        ?string $fallback,
        string $locale
    ): ?string {
        if (is_array($translations)) {
            if (
                isset($translations[$locale]) &&
                is_string($translations[$locale]) &&
                $translations[$locale] !== ''
            ) {
                return $translations[$locale];
            }

            if (
                isset($translations['en']) &&
                is_string($translations['en']) &&
                $translations['en'] !== ''
            ) {
                return $translations['en'];
            }
        }

        return $fallback;
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\ServiceProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $locale = $this->resolveLocale($request);

        $limit = $request->integer('limit');

        $query = ServiceProduct::query()
            ->where('active', true)
            ->with([
                'services' => fn ($query) =>
                    $query
                        ->where('active', true)
                        ->orderBy('sort_order')
                        ->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $serviceProducts = $query
            ->get()
            ->map(function (
                ServiceProduct $serviceProduct
            ) use ($locale) {
                return [
                    'id' =>
                        $serviceProduct->id,

                    'name' =>
                        $this->localizedValue(
                            $serviceProduct->name_translations,
                            $serviceProduct->name,
                            $locale
                        ),

                    'slug' =>
                        $serviceProduct->slug,

                    'description' =>
                        $this->localizedValue(
                            $serviceProduct->description_translations,
                            $serviceProduct->description,
                            $locale
                        ),

                    'services' =>
                        $serviceProduct->services
                            ->map(
                                fn ($service) => [
                                    'id' =>
                                        $service->id,

                                    'name' =>
                                        $this->localizedValue(
                                            $service->name_translations,
                                            $service->name,
                                            $locale
                                        ),
                                ]
                            )
                            ->values(),
                ];
            })
            ->values();

        return response()->json(
            $serviceProducts
        );
    }

    public function show(
        string $slug,
        Request $request
    ): JsonResponse {
        $locale = $this->resolveLocale($request);

        $serviceProduct = ServiceProduct::query()
            ->where('active', true)
            ->where('slug', $slug)
            ->with([
                'services' => fn ($query) =>
                    $query
                        ->where('active', true)
                        ->orderBy('sort_order')
                        ->orderBy('name'),
            ])
            ->first();

        if (!$serviceProduct) {
            return response()->json(
                [
                    'message' => 'Service not found.',
                ],
                404
            );
        }

        return response()->json([
            'id' =>
                $serviceProduct->id,

            'name' =>
                $this->localizedValue(
                    $serviceProduct->name_translations,
                    $serviceProduct->name,
                    $locale
                ),

            'slug' =>
                $serviceProduct->slug,

            'description' =>
                $this->localizedValue(
                    $serviceProduct->description_translations,
                    $serviceProduct->description,
                    $locale
                ),

            'services' =>
                $serviceProduct->services
                    ->map(
                        fn ($service) => [
                            'id' =>
                                $service->id,

                            'name' =>
                                $this->localizedValue(
                                    $service->name_translations,
                                    $service->name,
                                    $locale
                                ),
                        ]
                    )
                    ->values(),
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

<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientPortal\StoreServiceProductRequest;
use App\Http\Resources\Admin\ClientPortal\ServiceProductResource;
use App\Models\ServiceProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ServiceProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $sort = in_array(
            $request->string('sort')->toString(),
            [
                'name',
                'slug',
                'active',
                'sort_order',
                'updated_at',
            ],
            true
        )
            ? $request->string('sort')->toString()
            : 'sort_order';

        $direction =
            $request->string('direction')->toString() === 'desc'
                ? 'desc'
                : 'asc';

        $search = trim(
            $request->string('search')->toString()
        );

        return ServiceProductResource::collection(
            ServiceProduct::query()
                ->withCount([
                    'projects',
                    'services',
                ])
                ->with([
                    'services' => fn ($query) =>
                        $query->orderBy('sort_order'),
                ])
                ->when(
                    $search !== '',
                    fn ($query) =>
                        $query->where(
                            fn ($nested) =>
                                $nested
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'slug',
                                        'like',
                                        "%{$search}%"
                                    )
                        )
                )
                ->when(
                    $request->filled('active'),
                    fn ($query) =>
                        $query->where(
                            'active',
                            $request->boolean('active')
                        )
                )
                ->orderBy(
                    $sort,
                    $direction
                )
                ->orderBy('name')
                ->paginate(
                    min(
                        max(
                            $request->integer(
                                'per_page',
                                25
                            ),
                            10
                        ),
                        100
                    )
                )
        );
    }

    public function store(
        StoreServiceProductRequest $request
    ): ServiceProductResource {
        $data = $request->validated();

        $data['slug'] =
            $data['slug'] ?? ''
                ?: Str::slug(
                    $data['name'] ?? ''
                );

        $data['name_translations'] =
            $this->translationArray(
                $data['name'] ?? null,
                $data['name_sk'] ?? null
            );

        $data['description_translations'] =
            $this->translationArray(
                $data['description'] ?? null,
                $data['description_sk'] ?? null
            );

        unset(
            $data['name_sk'],
            $data['description_sk']
        );

        $serviceProduct =
            ServiceProduct::query()->create($data);

        return $this->resource($serviceProduct);
    }

    public function show(
        ServiceProduct $serviceProduct
    ): ServiceProductResource {
        return $this->resource($serviceProduct);
    }

    public function update(
        StoreServiceProductRequest $request,
        ServiceProduct $serviceProduct
    ): ServiceProductResource {
        $data = $request->validated();

        $data['slug'] =
            $data['slug'] ?? ''
                ?: Str::slug(
                    $data['name']
                        ?? $serviceProduct->name
                );

        $existingNameTranslations =
            is_array($serviceProduct->name_translations)
                ? $serviceProduct->name_translations
                : [];

        $existingDescriptionTranslations =
            is_array($serviceProduct->description_translations)
                ? $serviceProduct->description_translations
                : [];

        if (array_key_exists('name', $data)) {
            $existingNameTranslations['en'] =
                $data['name'];
        }

        if (array_key_exists('name_sk', $data)) {
            if (
                $data['name_sk'] !== null &&
                trim($data['name_sk']) !== ''
            ) {
                $existingNameTranslations['sk'] =
                    trim($data['name_sk']);
            } else {
                unset($existingNameTranslations['sk']);
            }
        }

        if (array_key_exists('description', $data)) {
            $existingDescriptionTranslations['en'] =
                $data['description'];
        }

        if (array_key_exists('description_sk', $data)) {
            if (
                $data['description_sk'] !== null &&
                trim($data['description_sk']) !== ''
            ) {
                $existingDescriptionTranslations['sk'] =
                    trim($data['description_sk']);
            } else {
                unset($existingDescriptionTranslations['sk']);
            }
        }

        $data['name_translations'] =
            $existingNameTranslations === []
                ? null
                : $existingNameTranslations;

        $data['description_translations'] =
            $existingDescriptionTranslations === []
                ? null
                : $existingDescriptionTranslations;

        unset(
            $data['name_sk'],
            $data['description_sk']
        );

        $serviceProduct->update($data);

        return $this->resource(
            $serviceProduct->fresh()
        );
    }

    public function deactivate(
        ServiceProduct $serviceProduct
    ): ServiceProductResource {
        $serviceProduct->update([
            'active' => false,
        ]);

        return $this->resource(
            $serviceProduct->fresh()
        );
    }

    public function destroy(
        ServiceProduct $serviceProduct
    ): \Illuminate\Http\Response {
        $serviceProduct->delete();

        return response()->noContent();
    }

    private function resource(
        ServiceProduct $serviceProduct
    ): ServiceProductResource {
        return ServiceProductResource::make(
            $serviceProduct
                ->loadCount([
                    'projects',
                    'services',
                ])
                ->load([
                    'services' => fn ($query) =>
                        $query->orderBy('sort_order'),
                ])
        );
    }

    private function translationArray(
        mixed $en,
        mixed $sk
    ): ?array {
        $translations = [];

        if (
            is_string($en) &&
            trim($en) !== ''
        ) {
            $translations['en'] = trim($en);
        }

        if (
            is_string($sk) &&
            trim($sk) !== ''
        ) {
            $translations['sk'] = trim($sk);
        }

        return $translations === []
            ? null
            : $translations;
    }
}

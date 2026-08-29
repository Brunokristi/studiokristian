<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ServiceResource;
use App\Models\Service;
use App\Models\ServiceProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceController extends Controller
{
    public function index(
        ServiceProduct $serviceProduct
    ): AnonymousResourceCollection {
        return ServiceResource::collection(
            $serviceProduct
                ->services()
                ->orderBy('sort_order')
                ->get()
        );
    }

    public function store(
        Request $request,
        ServiceProduct $serviceProduct
    ): ServiceResource {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'name_sk' => [
                'nullable',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'description_sk' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $name = trim(
            $validated['name']
        );

        $exists = $serviceProduct
            ->services()
            ->whereRaw(
                'LOWER(name) = ?',
                [mb_strtolower($name)]
            )
            ->exists();

        if ($exists) {
            return ServiceResource::make(
                $serviceProduct
                    ->services()
                    ->whereRaw(
                        'LOWER(name) = ?',
                        [mb_strtolower($name)]
                    )
                    ->firstOrFail()
            );
        }

        $nextSortOrder = (int) (
            $serviceProduct
                ->services()
                ->max('sort_order') ?? -1
        ) + 1;

        $service = $serviceProduct
            ->services()
            ->create([
                'name' => $name,
                'name_translations' =>
                    $this->translationArray(
                        $name,
                        $validated['name_sk'] ?? null
                    ),
                'description' =>
                    $validated['description'] ?? null,
                'description_translations' =>
                    $this->translationArray(
                        $validated['description'] ?? null,
                        $validated['description_sk'] ?? null
                    ),
                'active' =>
                    $validated['active'] ?? true,
                'sort_order' =>
                    $nextSortOrder,
            ]);

        return ServiceResource::make(
            $service
        );
    }

    public function update(
        Request $request,
        Service $service
    ): ServiceResource {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'name_sk' => [
                'nullable',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'description_sk' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $nameTranslations =
            is_array($service->name_translations)
                ? $service->name_translations
                : [];

        $descriptionTranslations =
            is_array($service->description_translations)
                ? $service->description_translations
                : [];

        $nameTranslations['en'] =
            trim($validated['name']);

        if (
            array_key_exists('name_sk', $validated)
        ) {
            if (
                $validated['name_sk'] !== null &&
                trim($validated['name_sk']) !== ''
            ) {
                $nameTranslations['sk'] =
                    trim($validated['name_sk']);
            } else {
                unset($nameTranslations['sk']);
            }
        }

        if (
            array_key_exists('description', $validated)
        ) {
            $descriptionTranslations['en'] =
                $validated['description'];
        }

        if (
            array_key_exists('description_sk', $validated)
        ) {
            if (
                $validated['description_sk'] !== null &&
                trim($validated['description_sk']) !== ''
            ) {
                $descriptionTranslations['sk'] =
                    trim($validated['description_sk']);
            } else {
                unset($descriptionTranslations['sk']);
            }
        }

        $service->update([
            'name' => trim($validated['name']),
            'name_translations' =>
                $nameTranslations === []
                    ? null
                    : $nameTranslations,
            'description' =>
                $validated['description'] ?? $service->description,
            'description_translations' =>
                $descriptionTranslations === []
                    ? null
                    : $descriptionTranslations,
            'active' =>
                $validated['active']
                ?? $service->active,
        ]);

        return ServiceResource::make(
            $service->fresh()
        );
    }

    public function destroy(
        Service $service
    ): \Illuminate\Http\Response {
        $service->delete();

        return response()->noContent();
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

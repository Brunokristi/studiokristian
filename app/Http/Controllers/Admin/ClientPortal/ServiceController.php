<?php

namespace App\Http\Controllers\Admin\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ClientPortal\ServiceResource;
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

            'active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $service->update([
            'name' => trim(
                $validated['name']
            ),
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
}
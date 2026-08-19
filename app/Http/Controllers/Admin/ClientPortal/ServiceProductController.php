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
        $sort = in_array($request->string('sort')->toString(), ['name', 'slug', 'active', 'sort_order', 'updated_at'], true)
            ? $request->string('sort')->toString() : 'sort_order';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
        $search = trim($request->string('search')->toString());

        return ServiceProductResource::collection(ServiceProduct::query()
            ->withCount('projects')
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->when($request->filled('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->orderBy($sort, $direction)
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 10), 100)));
    }

    public function store(StoreServiceProductRequest $request): ServiceProductResource
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);

        return ServiceProductResource::make(ServiceProduct::query()->create($data)->loadCount('projects'));
    }

    public function show(ServiceProduct $serviceProduct): ServiceProductResource
    {
        return ServiceProductResource::make($serviceProduct->loadCount('projects'));
    }

    public function update(StoreServiceProductRequest $request, ServiceProduct $serviceProduct): ServiceProductResource
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $serviceProduct->update($data);

        return ServiceProductResource::make($serviceProduct->fresh()->loadCount('projects'));
    }

    public function deactivate(ServiceProduct $serviceProduct): ServiceProductResource
    {
        $serviceProduct->update(['active' => false]);

        return ServiceProductResource::make($serviceProduct->fresh()->loadCount('projects'));
    }

    public function destroy(ServiceProduct $serviceProduct): \Illuminate\Http\Response
    {
        $serviceProduct->delete();

        return response()->noContent();
    }
}
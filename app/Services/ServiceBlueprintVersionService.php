<?php

namespace App\Services;

use App\Models\ServiceBlueprint;
use App\Models\ServiceBlueprintVersion;
use App\Models\ServiceProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ServiceBlueprintVersionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(ServiceProduct $product, string $name, string $version, User $actor): ServiceBlueprintVersion
    {
        return DB::transaction(function () use ($product, $name, $version, $actor) {
            $blueprint = ServiceBlueprint::query()->create(['service_product_id' => $product->id, 'name' => $name]);
            $draft = $blueprint->versions()->create(['version' => $version, 'status' => 'draft', 'created_by' => $actor->id]);
            $this->audit->record('service_blueprint_created', $actor, $blueprint, metadata: ['version' => $version]);
            return $draft;
        });
    }

    public function createDraft(ServiceBlueprint $blueprint, string $version, User $actor): ServiceBlueprintVersion
    {
        return DB::transaction(function () use ($blueprint, $version, $actor) {
            $source = $blueprint->versions()->whereIn('status', ['published', 'retired'])->latest('published_at')->first();
            $draft = $blueprint->versions()->create(['version' => $version, 'status' => 'draft', 'created_by' => $actor->id]);
            if (! $source) return $draft;

            foreach ($source->fields()->get() as $field) {
                $draft->fields()->create($field->only(['key', 'label', 'description', 'type', 'required', 'default_value', 'options', 'section', 'sort_order']));
            }
            foreach ($source->deliverables()->get() as $deliverable) {
                $draft->deliverables()->create($deliverable->only(['key', 'name', 'description', 'category', 'requirement_level', 'expected_resource_type', 'client_visible', 'default_selected', 'sort_order']));
            }

            $folderMap = [];
            $pending = $source->folders()->get();
            while ($pending->isNotEmpty()) {
                $progress = false;
                foreach ($pending as $index => $folder) {
                    if ($folder->parent_id && ! isset($folderMap[$folder->parent_id])) continue;
                    $copy = $draft->folders()->create([
                        'parent_id' => $folder->parent_id ? $folderMap[$folder->parent_id] : null,
                        'name' => $folder->name, 'client_visible' => $folder->client_visible, 'sort_order' => $folder->sort_order,
                    ]);
                    $folderMap[$folder->id] = $copy->id;
                    $pending->forget($index);
                    $progress = true;
                }
                if (! $progress) throw new InvalidArgumentException('Blueprint folder tree contains an invalid parent relationship.');
            }
            return $draft;
        });
    }

    public function publish(ServiceBlueprintVersion $version, string $changeSummary, User $actor): ServiceBlueprintVersion
    {
        if ($version->status !== 'draft' || trim($changeSummary) === '') {
            throw new InvalidArgumentException('Only a draft with a change summary can be published.');
        }

        return DB::transaction(function () use ($version, $changeSummary, $actor) {
            $locked = ServiceBlueprintVersion::query()->lockForUpdate()->findOrFail($version->id);
            if ($locked->status !== 'draft') throw new InvalidArgumentException('This blueprint version is no longer a draft.');
            $locked->update(['status' => 'published', 'change_summary' => $changeSummary, 'published_at' => now('UTC')]);
            $this->audit->record('service_blueprint_version_published', $actor, $locked, metadata: ['version' => $locked->version]);
            return $locked;
        });
    }

    public function retire(ServiceBlueprintVersion $version, User $actor): void
    {
        if ($version->status !== 'published') throw new InvalidArgumentException('Only a published blueprint version can be retired.');
        $version->update(['status' => 'retired', 'retired_at' => now('UTC')]);
        $this->audit->record('service_blueprint_version_retired', $actor, $version, metadata: ['version' => $version->version]);
    }
}
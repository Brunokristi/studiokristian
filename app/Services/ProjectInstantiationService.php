<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Project;
use App\Models\ServiceProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ProjectInstantiationService
{
    public function __construct(private readonly ServiceProductReadinessService $readiness, private readonly DynamicFieldValueValidator $fields, private readonly ContractService $contracts, private readonly AuditLogger $audit) {}

    public function create(array $data, User $actor): Project
    {
        $company = Company::query()->where('status', 'active')->findOrFail($data['company_id']);
        $product = ServiceProduct::query()->with(['blueprint.versions', 'defaultContractTemplate.versions'])->where('active', true)->findOrFail($data['service_product_id']);
        $setup = $this->readiness->inspect($product);
        if (! $setup['ready']) throw ValidationException::withMessages(['service_product_id' => 'Service setup incomplete: '.implode(', ', $setup['missing'])]);
        $configuration = $this->fields->validate($setup['blueprintVersion']->fields()->get(), $data['configuration'] ?? []);
        $contractValues = $this->fields->validate($setup['contractVersion']->field_definitions ?? [], $data['contract_values'] ?? []);

        return DB::transaction(function () use ($data, $actor, $company, $product, $setup, $configuration, $contractValues) {
            $url = $data['url'] ?: $this->uniqueSlug($data['name']);
            $project = Project::query()->create([
                ...collect($data)->only(['name', 'url', 'project_code', 'summary', 'internal_notes', 'portal_status', 'started_at', 'completed_at'])->all(),
                'url' => $url,
                'company_id' => $company->id, 'service_product_id' => $product->id,
                'service_blueprint_version_id' => $setup['blueprintVersion']->id,
                'configuration' => $configuration, 'contract_values' => $contractValues,
            ]);
            $selected = $data['selected_deliverable_ids'] ?? [];
            foreach ($setup['blueprintVersion']->deliverables()->get() as $definition) {
                if ($definition->requirement_level !== 'required' && ! in_array($definition->id, $selected, true) && ! ($definition->requirement_level === 'recommended' && $definition->default_selected)) continue;
                $project->deliverables()->create([
                    'source_blueprint_deliverable_id' => $definition->id, 'key_snapshot' => $definition->key,
                    'name_snapshot' => $definition->name, 'description_snapshot' => $definition->description,
                    'category_snapshot' => $definition->category, 'requirement_level' => $definition->requirement_level,
                    'expected_resource_type' => $definition->expected_resource_type, 'client_visible' => $definition->client_visible,
                    'sort_order' => $definition->sort_order,
                ]);
            }
            $map = [];
            $pending = $setup['blueprintVersion']->folders()->get();
            while ($pending->isNotEmpty()) {
                $progress = false;
                foreach ($pending as $index => $definition) {
                    if ($definition->parent_id && ! isset($map[$definition->parent_id])) continue;
                    $folder = $project->folders()->create([
                        'parent_id' => $definition->parent_id ? $map[$definition->parent_id] : null,
                        'source_blueprint_folder_id' => $definition->id,
                        'type' => $definition->type ?? 'folder',
                        'name' => $definition->name,
                        'resource_type' => $definition->resource_type,
                        'requirement_level' => $definition->requirement_level,
                        'requires_client_signature' => $definition->requires_client_signature,
                        'template_name' => $definition->template_name,
                        'content' => $definition->content,
                        'url' => $definition->url,
                        'client_visible' => $definition->client_visible,
                        'sort_order' => $definition->sort_order,
                        'created_by' => $actor->id,
                    ]);
                    $map[$definition->id] = $folder->id; $pending->forget($index); $progress = true;
                }
                if (! $progress) throw new InvalidArgumentException('Blueprint folder tree is invalid.');
            }
            $project->contacts()->sync($data['contact_ids'] ?? []);
            $this->contracts->generate($project, $setup['contractVersion']);
            $this->audit->record('project_created_from_blueprint', $actor, $project, $company->id, $project->id, ['blueprint_version' => $setup['blueprintVersion']->version, 'contract_version' => $setup['contractVersion']->version]);
            return $project->fresh();
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'project'; $slug = $base; $suffix = 2;
        while (Project::query()->where('url', $slug)->exists()) $slug = $base.'-'.$suffix++;
        return $slug;
    }
}
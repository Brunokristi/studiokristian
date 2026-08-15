<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ClientContact;
use App\Models\Project;
use App\Models\ServiceProduct;
use App\Models\User;
use App\Notifications\ProjectInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProjectInstantiationService
{
    public function __construct(private readonly ServiceProductReadinessService $readiness, private readonly DynamicFieldValueValidator $fields, private readonly AuditLogger $audit) {}

    public function create(array $data, User $actor): Project
    {
        $company = Company::query()->findOrFail($data['company_id']);
        $product = ServiceProduct::query()->with(['blueprint.versions', 'defaultContractTemplate.versions'])->where('active', true)->findOrFail($data['service_product_id']);
        $setup = $this->readiness->inspect($product);
        $blueprintVersion = $setup['blueprintVersion'] ?? null;

        $configuration = $blueprintVersion
            ? $this->fields->validate($blueprintVersion->fields()->get(), $data['configuration'] ?? [])
            : ($data['configuration'] ?? []);

        return DB::transaction(function () use ($data, $actor, $company, $product, $blueprintVersion, $configuration) {
            $url = ($data['url'] ?? null) ?: $this->uniqueSlug($data['name']);
            $name = (string) ($data['name'] ?? '');
            $summary = (string) ($data['summary'] ?? '');
            $project = Project::query()->create([
                ...collect($data)->only(['name', 'url', 'project_code', 'summary', 'internal_notes', 'portal_status', 'started_at', 'completed_at'])->all(),
                'url' => $url,
                // Portfolio uses projects directly, so each created project must be portfolio-ready.
                'name_translations' => ['en' => $name],
                'summary_translations' => $summary !== '' ? ['en' => $summary] : null,
                'is_published' => false,
                'company_id' => $company->id, 'service_product_id' => $product->id,
                'service_blueprint_version_id' => $blueprintVersion?->id,
                'configuration' => $configuration,
            ]);

            if ($blueprintVersion) {
                $map = [];
                $pending = $blueprintVersion->folders()->get();
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
            }

            $contactIds = collect($data['contact_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
            $coworkerIds = collect($data['coworker_ids'] ?? [])->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all();

            if ($actor->is_admin) {
                $coworkerIds = collect($coworkerIds)->push($actor->id)->unique()->values()->all();
            }

            $project->contacts()->sync($contactIds);
            $project->coworkers()->sync($coworkerIds);

            if (! empty($contactIds)) {
                ClientContact::query()->whereIn('id', $contactIds)->update([
                    'active' => true,
                    'can_access_portal' => true,
                    'access_revoked_at' => null,
                ]);
            }

            DB::afterCommit(function () use ($project, $contactIds, $coworkerIds) {
                if (! empty($contactIds)) {
                    $contacts = ClientContact::query()->whereIn('id', $contactIds)->get();
                    foreach ($contacts as $contact) {
                        $contact->notify(new ProjectInvitationNotification($project, route('client.login')));
                    }
                }

                if (! empty($coworkerIds)) {
                    $coworkers = User::query()->whereIn('id', $coworkerIds)->where('is_admin', false)->get();
                    foreach ($coworkers as $coworker) {
                        $coworker->notify(new ProjectInvitationNotification($project, route('login')));
                    }
                }
            });

            $this->audit->record('project_created_from_blueprint', $actor, $project, $company->id, $project->id, [
                'blueprint_version' => $blueprintVersion?->version,
            ]);
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
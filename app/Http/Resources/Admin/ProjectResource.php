<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'service_product_id' => $this->service_product_id,
            'name' => $this->name,
            'url' => $this->url,
            'project_code' => $this->project_code,
            'summary' => $this->summary,
            'internal_notes' => $this->internal_notes,
            'status' => $this->portal_status,
            'is_published' => $this->is_published,
            'is_saas' => $this->is_saas,
            'trial_enabled' => $this->trial_enabled,
            'trial_duration_days' => $this->trial_duration_days,
            'trial_credits' => $this->trial_credits,
            'payment_failure_grace_period_days' => $this->payment_failure_grace_period_days,
            'started_at' => $this->started_at?->toDateString(),
            'completed_at' => $this->completed_at?->toDateString(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'configuration' => $this->configuration,
            'contract_values' => $this->contract_values,
            'company' => $this->whenLoaded('company', fn () => [
                'id' => $this->company->id,
                'name' => $this->company->display_label,
            ]),
            'service_product' => $this->whenLoaded('serviceProduct', fn () => [
                'id' => $this->serviceProduct->id,
                'name' => $this->serviceProduct->name,
                'active' => $this->serviceProduct->active,
            ]),
            'contacts_count' => $this->whenCounted('contacts'),
            'saas_plans_count' => $this->whenCounted('saasPlans'),
            'saas_subscriptions_count' => $this->whenCounted('saasSubscriptions'),
            'contacts' => \App\Http\Resources\Admin\ContactResource::collection($this->whenLoaded('contacts')),
            'coworkers' => $this->whenLoaded('coworkers', fn () => $this->coworkers->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])),
            'current_user' => $request->user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'is_admin' => (bool) $request->user()->is_admin,
            ] : null,
            'blueprint_version' => $this->whenLoaded('blueprintVersion', fn () => $this->blueprintVersion ? ['id'=>$this->blueprintVersion->id,'version'=>$this->blueprintVersion->version,'name'=>$this->blueprintVersion->blueprint->name] : null),
            'deliverables' => $this->whenLoaded('deliverables'),
            'folders' => $this->whenLoaded('folders', function () {
                return $this->folders
                    ->map(function ($folder) {
                        $payload = $folder->toArray();

                        unset($payload['signatures']);

                        $isDocument =
                            $folder->type === 'file' &&
                            $folder->resource_type === 'document';

                        $latestSignature =
                            $folder->relationLoaded('signatures')
                                ? $folder->signatures
                                    ->sortByDesc(fn ($signature) => $signature->signed_at?->getTimestamp() ?? 0)
                                    ->first()
                                : null;

                        $signed =
                            $isDocument &&
                            $latestSignature !== null;

                        $payload['is_signed'] = $signed;
                        $payload['signed'] = $signed;
                        $payload['signed_at'] = $latestSignature?->signed_at?->toIso8601String();

                        return $payload;
                    })
                    ->values();
            }),
            'todo_signatures' => $this->whenLoaded('folders', function () {
                return $this->folders
                    ->filter(function ($folder): bool {
                        if (
                            $folder->type !== 'file' ||
                            $folder->resource_type !== 'document' ||
                            ! $folder->requires_client_signature
                        ) {
                            return false;
                        }

                        $hasSignature = $folder->relationLoaded('signatures') &&
                            $folder->signatures->isNotEmpty();

                        return ! $hasSignature;
                    })
                    ->map(fn ($folder) => [
                        'id' => $folder->id,
                        'name' => $folder->name,
                        'type' => $folder->type,
                        'resource_type' => $folder->resource_type,
                        'requires_signature' => true,
                        'signed' => false,
                    ])
                    ->values();
            }),
            'contracts' => $this->whenLoaded('contracts'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
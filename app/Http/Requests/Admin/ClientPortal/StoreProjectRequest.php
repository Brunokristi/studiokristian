<?php

namespace App\Http\Requests\Admin\ClientPortal;

use App\Models\ClientContact;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectRequest extends AdminClientPortalRequest
{
    public function rules(): array
    {
        $project = $this->route('project');
        $projectId = $project?->getKey();
        $serviceProductRule = Rule::exists('service_products', 'id');

        if (! $project) {
            $serviceProductRule->where('active', true);
        } else {
            $serviceProductRule->where(fn ($query) => $query
                ->where('active', true)
                ->orWhere('id', $project->service_product_id));
        }

        return [
            'company_id' => ['required', 'exists:companies,id'],
            'service_product_id' => ['required', $serviceProductRule],
            'name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'alpha_dash:ascii', 'max:255', Rule::unique('projects', 'url')->ignore($projectId)],
            'project_code' => ['nullable', 'string', 'max:100'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'portal_status' => ['required', 'in:draft,active,on_hold,completed,archived'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'contact_ids' => ['array'],
            'contact_ids.*' => ['integer', 'distinct', 'exists:client_contacts,id'],
            'coworker_ids' => ['array'],
            'coworker_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'configuration' => ['array'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('company_id') || $validator->errors()->has('contact_ids')) {
                return;
            }

            $invalidContactExists = ClientContact::query()
                ->whereIn('id', $this->input('contact_ids', []))
                ->where('company_id', '!=', $this->integer('company_id'))
                ->exists();

            if ($invalidContactExists) {
                $validator->errors()->add('contact_ids', 'Every assigned contact must belong to the selected company.');
            }
        }];
    }
}
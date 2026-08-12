<?php

namespace App\Services;

use App\Models\ContractInstance;
use App\Models\ContractTemplateVersion;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ContractService
{
    public function __construct(
        private readonly DocumentVariableRenderer $renderer,
        private readonly ContractBlockDocumentService $blocks,
        private readonly AuditLogger $audit,
    ) {}

    public function generate(Project $project, ContractTemplateVersion $version, ?string $number = null): ContractInstance
    {
        if ($version->status !== 'published' || ! $version->published_at) {
            throw new InvalidArgumentException('Only published contract template versions can generate contracts.');
        }

        if ($version->template->service_product_id && $version->template->service_product_id !== $project->service_product_id) {
            throw new InvalidArgumentException('The contract template does not belong to the project service product.');
        }

        $project->loadMissing('company');
        $source = $version->document_schema
            ? $this->blocks->render($version->document_schema, [...($project->configuration ?? []), ...($project->contract_values ?? [])])
            : $version->content;
        $content = $this->renderer->render($source, $this->variables($project, $version));
        $contract = ContractInstance::query()->create([
            'project_id' => $project->id,
            'contract_template_version_id' => $version->id,
            'number' => $number,
            'title' => $version->template->name,
            'version' => $version->version,
            'status' => 'ready',
            'rendered_content' => $content,
            'content_hash' => hash('sha256', $content),
        ]);

        $path = 'client-portal/contracts/'.$project->id.'/'.$contract->id.'/preview-'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, Pdf::loadView('pdf.contract', ['contract' => $contract])->output());
        $contract->update(['generated_pdf_path' => $path]);
        $this->audit->record('contract.created', subject: $contract, companyId: $project->company_id, projectId: $project->id);

        return $contract->fresh();
    }

    private function variables(Project $project, ContractTemplateVersion $version): array
    {
        return [
            'provider.company_name' => config('client-portal.provider.company_name'),
            'provider.ico' => config('client-portal.provider.registration_number'),
            'provider.dic' => config('client-portal.provider.tax_number'),
            'provider.ic_dph' => config('client-portal.provider.vat_number'),
            'provider.address' => config('client-portal.provider.address'),
            'client.company_name' => $project->company->name,
            'client.ico' => $project->company->registration_number,
            'client.dic' => $project->company->tax_number,
            'client.ic_dph' => $project->company->vat_number,
            'client.address' => $project->company->registered_address,
            'project.name' => $project->name,
            'document.version' => $version->version,
            'document.date' => now()->toDateString(),
            ...collect($project->configuration ?? [])->mapWithKeys(fn ($value, $key) => ['project.config.'.$key => $value])->all(),
            ...collect($project->contract_values ?? [])->mapWithKeys(fn ($value, $key) => ['contract.'.$key => $value])->all(),
        ];
    }
}
<?php

namespace App\Services;

use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ContractTemplateVersionService
{
    public function __construct(private readonly AuditLogger $audit, private readonly ContractBlockDocumentService $documents) {}

    public function createDraft(ContractTemplate $template, string $version, User $actor): ContractTemplateVersion
    {
        $source = $template->versions()->whereIn('status', ['published', 'retired'])->latest('published_at')->first();

        return $template->versions()->create([
            'version' => $version,
            'content' => $source?->content ?? '',
            'document_schema' => $source?->document_schema,
            'field_definitions' => $source?->field_definitions,
            'status' => 'draft',
            'change_policy' => 'future_only',
            'created_by' => $actor->id,
        ]);
    }

    public function publish(ContractTemplateVersion $version, string $changePolicy, string $changeSummary, User $actor): ContractTemplateVersion
    {
        if ($version->status !== 'draft') {
            throw new InvalidArgumentException('Only a draft can be published.');
        }
        if ((trim($version->content) === '' && ! $version->document_schema) || trim($changeSummary) === '') {
            throw new InvalidArgumentException('Published versions require content and a change summary.');
        }
        if (! in_array($changePolicy, ['future_only', 'requires_new_acceptance', 'information_only'], true)) {
            throw new InvalidArgumentException('Invalid contract change policy.');
        }
        if ($version->document_schema) {
            $this->documents->validate($version->document_schema);
        }

        return DB::transaction(function () use ($version, $changePolicy, $changeSummary, $actor) {
            $locked = ContractTemplateVersion::query()->lockForUpdate()->findOrFail($version->id);
            if ($locked->status !== 'draft') {
                throw new InvalidArgumentException('This version is no longer a draft.');
            }
            $locked->update([
                'status' => 'published', 'change_policy' => $changePolicy,
                'change_summary' => $changeSummary, 'published_at' => now('UTC'),
            ]);
            $this->audit->record('contract_template_version.published', $actor, $locked, metadata: [
                'version' => $locked->version, 'change_policy' => $changePolicy,
            ]);

            return $locked;
        });
    }

    public function retire(ContractTemplateVersion $version, User $actor): void
    {
        if ($version->status !== 'published') {
            throw new InvalidArgumentException('Only a published version can be retired.');
        }
        $version->update(['status' => 'retired', 'retired_at' => now('UTC')]);
        $this->audit->record('contract_template_version.retired', $actor, $version, metadata: ['version' => $version->version]);
    }
}
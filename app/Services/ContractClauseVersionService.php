<?php

namespace App\Services;

use App\Models\ContractClause;
use App\Models\ContractClauseVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractClauseVersionService
{
    public function __construct(private readonly ContractBlockDocumentService $documents, private readonly AuditLogger $audit) {}
    public function createDraft(ContractClause $clause, string $version, User $actor): ContractClauseVersion
    {
        $source = $clause->versions()->whereIn('status', ['published', 'retired'])->latest('published_at')->first();
        return $clause->versions()->create(['version' => $version, 'status' => 'draft', 'content' => $source?->content ?? ['blocks' => []], 'created_by' => $actor->id]);
    }
    public function publish(ContractClauseVersion $version, string $summary, User $actor): ContractClauseVersion
    {
        if ($version->status !== 'draft' || trim($summary) === '') throw ValidationException::withMessages(['change_summary' => 'A draft and change summary are required.']);
        $this->documents->validate($version->content);
        return DB::transaction(function () use ($version, $summary, $actor) {
            $locked = ContractClauseVersion::query()->lockForUpdate()->findOrFail($version->id);
            $locked->update(['status' => 'published', 'change_summary' => $summary, 'published_at' => now('UTC')]);
            $this->audit->record('contract_clause_version_published', $actor, $locked, metadata: ['version' => $locked->version]);
            return $locked;
        });
    }
}
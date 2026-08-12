<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ContractClauseVersion extends Model
{
    protected $fillable = ['contract_clause_id', 'version', 'status', 'content', 'change_summary', 'published_at', 'retired_at', 'created_by'];
    protected function casts(): array { return ['content' => 'array', 'published_at' => 'datetime', 'retired_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if (in_array($version->getOriginal('status'), ['published', 'retired'], true) && array_diff(array_keys($version->getDirty()), ['status', 'retired_at', 'updated_at']) !== []) throw new LogicException('Published clause versions are immutable.');
        });
        static::deleting(fn (self $version) => $version->status === 'draft' ?: throw new LogicException('Published clause versions cannot be deleted.'));
    }
    public function clause(): BelongsTo { return $this->belongsTo(ContractClause::class, 'contract_clause_id'); }
}
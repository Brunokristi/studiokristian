<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractTemplateVersion extends Model
{
    protected $fillable = [
        'contract_template_id', 'version', 'content', 'status', 'change_policy',
        'change_summary', 'published_at', 'retired_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'retired_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if (in_array($version->getOriginal('status'), ['published', 'retired'], true)) {
                $allowed = ['status', 'retired_at', 'updated_at'];
                $changed = array_keys($version->getDirty());

                if (array_diff($changed, $allowed) !== []) {
                    throw new LogicException('Published contract template versions are immutable.');
                }
            }
        });

        static::deleting(function (self $version): void {
            if ($version->status !== 'draft') {
                throw new LogicException('Published or retired contract template versions cannot be deleted.');
            }
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ContractInstance::class);
    }
}
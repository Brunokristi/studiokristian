<?php

namespace App\Models;

use LogicException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContractInstance extends Model
{
    protected $fillable = [
        'project_id', 'contract_template_version_id', 'number', 'title', 'version',
        'status', 'rendered_content', 'content_hash', 'generated_pdf_path',
        'final_pdf_path', 'final_pdf_hash', 'sent_at', 'viewed_at', 'accepted_at', 'superseded_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime', 'viewed_at' => 'datetime', 'accepted_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $contract): void {
            if ($contract->getOriginal('status') === 'accepted') {
                throw new LogicException('Accepted contracts are immutable.');
            }
        });
        static::deleting(fn (self $contract) => $contract->status !== 'accepted');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(ContractTemplateVersion::class, 'contract_template_version_id');
    }

    public function acceptance(): HasOne
    {
        return $this->hasOne(ContractAcceptance::class);
    }
}
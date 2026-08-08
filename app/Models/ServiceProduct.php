<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceProduct extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'active', 'default_contract_template_id',
        'recommended_document_type_ids',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'recommended_document_type_ids' => 'array'];
    }

    public function contractTemplates(): HasMany
    {
        return $this->hasMany(ContractTemplate::class);
    }

    public function defaultContractTemplate(): BelongsTo
    {
        return $this->belongsTo(ContractTemplate::class, 'default_contract_template_id');
    }
}
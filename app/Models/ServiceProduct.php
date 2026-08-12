<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceProduct extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'active', 'sort_order', 'default_contract_template_id',
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

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function blueprint(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ServiceBlueprint::class);
    }
}
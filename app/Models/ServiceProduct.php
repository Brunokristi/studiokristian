<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceProduct extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'name_translations',
        'description_translations',
        'active',
        'sort_order',
        'default_contract_template_id',
        'recommended_document_type_ids',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'recommended_document_type_ids' => 'array',
            'name_translations' => 'array',
            'description_translations' => 'array',
        ];
    }

    public function contractTemplates(): HasMany
    {
        return $this->hasMany(
            ContractTemplate::class
        );
    }

    public function defaultContractTemplate(): BelongsTo
    {
        return $this->belongsTo(
            ContractTemplate::class,
            'default_contract_template_id'
        );
    }

    public function projects(): HasMany
    {
        return $this->hasMany(
            Project::class
        );
    }

    public function blueprint(): HasOne
    {
        return $this->hasOne(
            ServiceBlueprint::class
        );
    }

    public function services(): HasMany
    {
        return $this->hasMany(
            Service::class
        )->orderBy('sort_order');
    }
}

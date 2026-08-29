<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceProduct extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'active',
        'sort_order',
        'default_contract_template_id',
        'recommended_document_type_ids',
        'name_translations',
        'description_translations',
    ];

    protected function casts(): array
    {
        return [
            'active' =>
                'boolean',

            'recommended_document_type_ids' =>
                'array',

            'name_translations' =>
                'array',

            'description_translations' =>
                'array',
        ];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(
            Project::class
        );
    }

    public function services(): HasMany
    {
        return $this->hasMany(
            Service::class
        )->orderBy('sort_order');
    }

    public function templateFolders(): HasMany
    {
        return $this->hasMany(
            ServiceProductTemplateFolder::class
        )
            ->whereNull('parent_id')
            ->orderBy('sort_order');
    }

    public function allTemplateFolders(): HasMany
    {
        return $this->hasMany(
            ServiceProductTemplateFolder::class
        );
    }
}
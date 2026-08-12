<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractTemplate extends Model
{
    protected $fillable = ['service_product_id', 'name', 'slug'];

    public function serviceProduct(): BelongsTo
    {
        return $this->belongsTo(ServiceProduct::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContractTemplateVersion::class);
    }

    public function currentPublishedVersion(): ?ContractTemplateVersion
    {
        return $this->versions()->where('status', 'published')->latest('published_at')->first();
    }
}
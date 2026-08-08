<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'service_product_id',
        'portal_status',
        'name',
        'name_translations',
        'url',
        'live_url',
        'summary',
        'summary_translations',
        'hex_color',
        'logo_path',
        'archived_at',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'summary_translations' => 'array',
        'archived_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function serviceProduct(): BelongsTo
    {
        return $this->belongsTo(ServiceProduct::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(ClientContact::class)->withTimestamps();
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProjectImage::class)->orderBy('sort_order');
    }

    public function features(): HasMany
    {
        return $this->hasMany(ProjectFeature::class)->orderBy('sort_order');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(ContractInstance::class);
    }

    public function priceOffers(): HasMany
    {
        return $this->hasMany(PriceOffer::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function guides(): HasMany
    {
        return $this->hasMany(Guide::class);
    }

    public function serviceAccounts(): HasMany
    {
        return $this->hasMany(ServiceAccount::class);
    }
}

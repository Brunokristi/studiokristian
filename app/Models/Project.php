<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'service_product_id',
        'service_blueprint_version_id',
        'portal_status',
        'is_published',
        'project_code',
        'name',
        'name_translations',
        'url',
        'live_url',
        'summary',
        'summary_translations',
        'internal_notes',
        'configuration',
        'contract_values',
        'started_at',
        'completed_at',
        'hex_color',
        'logo_path',
        'archived_at',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'summary_translations' => 'array',
        'archived_at' => 'datetime',
        'started_at' => 'date',
        'completed_at' => 'date',
        'configuration' => 'array',
        'contract_values' => 'array',
        'is_published' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function serviceProduct(): BelongsTo
    {
        return $this->belongsTo(ServiceProduct::class);
    }

    public function blueprintVersion(): BelongsTo { return $this->belongsTo(ServiceBlueprintVersion::class, 'service_blueprint_version_id'); }
    public function deliverables(): HasMany { return $this->hasMany(ProjectDeliverable::class)->orderBy('sort_order'); }
    public function folders(): HasMany { return $this->hasMany(ProjectFolder::class)->orderBy('sort_order'); }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(ClientContact::class)->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        $relation = $this->belongsToMany(User::class)->withTimestamps();

        if (Schema::hasColumn('project_user', 'access_type')) {
            $relation->withPivot('access_type');
        }

        return $relation;
    }

    public function coworkers(): BelongsToMany
    {
        $relation = $this->belongsToMany(User::class)->withTimestamps();

        if (Schema::hasColumn('project_user', 'access_type')) {
            $relation
                ->withPivot('access_type')
                ->where(function ($query) {
                    $query
                        ->where('project_user.access_type', 'coworker')
                        ->orWhereNull('project_user.access_type');
                });
        }

        return $relation;
    }

    public function clients(): BelongsToMany
    {
        $relation = $this->belongsToMany(User::class)->withTimestamps();

        if (Schema::hasColumn('project_user', 'access_type')) {
            $relation
                ->withPivot('access_type')
                ->wherePivot('access_type', 'client');
        } else {
            $relation->whereRaw('1 = 0');
        }

        return $relation;
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(ProjectTicket::class);
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

    public function documentSignatures(): HasMany
    {
        return $this->hasMany(ProjectFolderSignature::class);
    }
}

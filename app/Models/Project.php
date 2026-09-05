<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property int|null $company_id
 */
class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'service_product_id',
        'portal_status',
        'is_published',
        'is_saas',
        'trial_enabled',
        'trial_duration_days',
        'trial_credits',
        'project_code',
        'name',
        'name_translations',
        'url',
        'live_url',
        'summary',
        'summary_translations',
        'internal_notes',
        'started_at',
        'completed_at',
        'hex_color',
        'logo_path',
        'podcast_path',
        'archived_at',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'summary_translations' => 'array',
        'archived_at' => 'datetime',
        'started_at' => 'date',
        'completed_at' => 'date',
        'is_published' => 'boolean',
        'is_saas' => 'boolean',
        'trial_enabled' => 'boolean',
        'trial_duration_days' => 'integer',
        'trial_credits' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function serviceProduct(): BelongsTo
    {
        return $this->belongsTo(ServiceProduct::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(
            ProjectFolder::class
        )->orderBy('sort_order');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(
            ClientContact::class
        )->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        $relation = $this->belongsToMany(
            User::class
        )->withTimestamps();

        if (
            Schema::hasColumn(
                'project_user',
                'access_type'
            )
        ) {
            $relation->withPivot('access_type');
        }

        return $relation;
    }

    public function coworkers(): BelongsToMany
    {
        $relation = $this->belongsToMany(
            User::class
        )->withTimestamps();

        if (
            Schema::hasColumn(
                'project_user',
                'access_type'
            )
        ) {
            $relation
                ->withPivot('access_type')
                ->where(function ($query) {
                    $query
                        ->where(
                            'project_user.access_type',
                            'coworker'
                        )
                        ->orWhereNull(
                            'project_user.access_type'
                        );
                });
        }

        return $relation;
    }

    public function clients(): BelongsToMany
    {
        $relation = $this->belongsToMany(
            User::class
        )->withTimestamps();

        if (
            Schema::hasColumn(
                'project_user',
                'access_type'
            )
        ) {
            $relation
                ->withPivot('access_type')
                ->wherePivot(
                    'access_type',
                    'client'
                );
        } else {
            $relation->whereRaw('1 = 0');
        }

        return $relation;
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(
            ProjectTicket::class
        );
    }

    public function images(): HasMany
    {
        return $this->hasMany(
            ProjectImage::class
        )->orderBy('sort_order');
    }

    public function features(): HasMany
    {
        return $this->hasMany(
            ProjectFeature::class
        )->orderBy('sort_order');
    }

    public function files(): HasMany
    {
        return $this->hasMany(
            ProjectFile::class
        );
    }

    public function documentSignatures(): HasMany
    {
        return $this->hasMany(
            ProjectFolderSignature::class
        );
    }

    public function saasPlans(): HasMany
    {
        return $this->hasMany(
            SaasPlan::class,
            'project_id'
        )->orderBy('sort_order');
    }

    public function saasSubscriptions(): HasMany
    {
        return $this->hasMany(
            SaasSubscription::class,
            'project_id'
        );
    }

    public function companyTrials(): HasMany
    {
        return $this->hasMany(CompanyTrial::class);
    }

    public function billingApiCredentials(): HasMany
    {
        return $this->hasMany(SaasProjectApiCredential::class);
    }

    public function billingCustomerCredentials(): HasMany
    {
        return $this->hasMany(SaasCustomerApiCredential::class);
    }

    public function billingCustomers(): HasMany
    {
        return $this->hasMany(SaasBillingCustomer::class);
    }
}
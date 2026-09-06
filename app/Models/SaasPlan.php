<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'slug',
        'description',
        'features',
        'active',
        'sort_order',
        'stripe_product_id',
    ];

    protected $casts = [
        'features' => 'array',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(SaasPlanPrice::class)->orderBy('amount');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SaasSubscription::class);
    }

    public function planFeatures(): HasMany
    {
        return $this->hasMany(SaasPlanFeature::class);
    }

    /**
     * Entitlements keyed by the project's stable feature key, e.g.
     * ['ai_credits_monthly' => ['type' => 'limit', 'value' => 500, 'unit' => 'credits']].
     */
    public function entitlementsApiArray(): array
    {
        return $this->planFeatures
            ->filter(fn (SaasPlanFeature $planFeature) => $planFeature->feature?->active)
            ->mapWithKeys(fn (SaasPlanFeature $planFeature) => [
                $planFeature->feature->key => $planFeature->toApiValue(),
            ])
            ->all();
    }
}
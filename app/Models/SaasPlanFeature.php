<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasPlanFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'saas_plan_id',
        'saas_feature_id',
        'boolean_value',
        'limit_value',
        'is_unlimited',
        'is_custom',
    ];

    protected $casts = [
        'boolean_value' => 'boolean',
        'limit_value' => 'integer',
        'is_unlimited' => 'boolean',
        'is_custom' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'saas_plan_id');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(SaasFeature::class, 'saas_feature_id');
    }

    /**
     * Generic, project-agnostic entitlement value consumed by SaaS applications.
     * Unlimited/custom are explicit types rather than magic numeric values.
     */
    public function toApiValue(): array
    {
        if (! $this->feature) {
            return ['type' => 'unknown'];
        }

        if ($this->feature->type === SaasFeature::TYPE_BOOLEAN) {
            return [
                'type' => 'boolean',
                'value' => (bool) $this->boolean_value,
            ];
        }

        if ($this->is_unlimited) {
            return ['type' => 'unlimited'];
        }

        if ($this->is_custom) {
            return ['type' => 'custom'];
        }

        return [
            'type' => 'limit',
            'value' => (int) $this->limit_value,
            'unit' => $this->feature->unit,
        ];
    }
}

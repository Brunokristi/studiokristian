<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasPlanPrice extends Model
{
    use HasFactory;

    public const INTERVAL_MONTHLY = 'monthly';
    public const INTERVAL_YEARLY = 'yearly';

    public const INTERVALS = [
        self::INTERVAL_MONTHLY,
        self::INTERVAL_YEARLY,
    ];

    protected $fillable = [
        'saas_plan_id',
        'amount',
        'currency',
        'interval',
        'active',
        'stripe_price_id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'active' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'saas_plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SaasSubscription::class);
    }
}
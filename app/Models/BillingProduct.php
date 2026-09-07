<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reusable custom-service catalog entry (hosting, branding, development...).
 * Not related to SaaS plans/products.
 */
class BillingProduct extends Model
{
    use HasFactory;

    public const TYPE_ONE_TIME = 'one_time';
    public const TYPE_RECURRING = 'recurring';

    public const TYPES = [
        self::TYPE_ONE_TIME,
        self::TYPE_RECURRING,
    ];

    public const INTERVALS = ['day', 'week', 'month', 'year'];

    protected $fillable = [
        'name',
        'description',
        'unit_amount',
        'currency',
        'billing_type',
        'interval',
        'interval_count',
        'active',
        'stripe_product_id',
    ];

    protected $casts = [
        'unit_amount' => 'integer',
        'interval_count' => 'integer',
        'active' => 'boolean',
    ];

    public function billingItems(): HasMany
    {
        return $this->hasMany(ProjectBillingItem::class);
    }

    public function isRecurring(): bool
    {
        return $this->billing_type === self::TYPE_RECURRING;
    }
}

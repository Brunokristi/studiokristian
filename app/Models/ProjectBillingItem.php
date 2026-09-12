<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A catalog product enabled for one project, at that project's own price.
 */
class ProjectBillingItem extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INVOICED = 'invoiced';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'project_id',
        'billing_product_id',
        'project_subscription_id',
        'name',
        'description',
        'unit_amount',
        'currency',
        'quantity',
        'billing_type',
        'interval',
        'interval_count',
        'status',
        'starts_at',
        'ends_at',
        'stripe_price_id',
        'stripe_subscription_item_id',
    ];

    protected $casts = [
        'unit_amount' => 'integer',
        'quantity' => 'integer',
        'interval_count' => 'integer',
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(BillingProduct::class, 'billing_product_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ProjectSubscription::class, 'project_subscription_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProjectBillingItemVersion::class, 'project_billing_item_id')
            ->orderBy('starts_at');
    }

    public function isEffectiveOn(?\Illuminate\Support\Carbon $date = null): bool
    {
        $date ??= today();

        return (! $this->starts_at || $this->starts_at->lte($date))
            && (! $this->ends_at || $this->ends_at->gte($date))
            && $this->status !== self::STATUS_CANCELED;
    }

    public function isRecurring(): bool
    {
        return $this->billing_type === BillingProduct::TYPE_RECURRING;
    }

    public function totalAmount(): int
    {
        return (int) $this->unit_amount * (int) $this->quantity;
    }
}

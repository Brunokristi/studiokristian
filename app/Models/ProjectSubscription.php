<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Stripe Subscription per project grouping all of that project's recurring items.
 */
class ProjectSubscription extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'project_id',
        'company_id',
        'status',
        'collection_method',
        'currency',
        'stripe_subscription_id',
        'stripe_customer_id',
        'stripe_default_payment_method_id',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'canceled_at',
        'ended_at',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'canceled_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectBillingItem::class, 'project_subscription_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(ProjectInvoice::class, 'project_subscription_id');
    }

    public function monthlyTotal(): int
    {
        return $this->items->sum(fn (ProjectBillingItem $item) => $item->totalAmount());
    }
}

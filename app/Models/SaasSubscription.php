<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class SaasSubscription extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_INCOMPLETE_EXPIRED = 'incomplete_expired';
    public const STATUS_PAUSED = 'paused';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_PAST_DUE,
        self::STATUS_CANCELED,
        self::STATUS_UNPAID,
        self::STATUS_INCOMPLETE,
        self::STATUS_INCOMPLETE_EXPIRED,
        self::STATUS_PAUSED,
    ];

    protected $fillable = [
        'project_id',
        'company_id',
        'saas_plan_id',
        'saas_plan_price_id',
        'status',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'ended_at',
        'payment_failed_at',
        'stripe_customer_id',
        'stripe_subscription_id',
        'cancel_at_period_end',
        'scheduled_saas_plan_id',
        'scheduled_saas_plan_price_id',
        'stripe_schedule_id',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'ended_at' => 'datetime',
        'payment_failed_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'saas_plan_id');
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(SaasPlanPrice::class, 'saas_plan_price_id');
    }

    public function scheduledPlan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'scheduled_saas_plan_id');
    }

    public function scheduledPrice(): BelongsTo
    {
        return $this->belongsTo(SaasPlanPrice::class, 'scheduled_saas_plan_price_id');
    }

    public function gracePeriodEndsAt(): ?Carbon
    {
        if (! $this->payment_failed_at) {
            return null;
        }

        return $this->payment_failed_at->copy()->addDays(
            $this->project?->payment_failure_grace_period_days
                ?? Project::DEFAULT_PAYMENT_FAILURE_GRACE_PERIOD_DAYS
        );
    }
}
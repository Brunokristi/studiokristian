<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'company_id',
        'saas_subscription_id',
        'stripe_invoice_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'amount_due',
        'amount_paid',
        'currency',
        'status',
        'paid_at',
        'attempted_at',
    ];

    protected $casts = [
        'amount_due' => 'integer',
        'amount_paid' => 'integer',
        'paid_at' => 'datetime',
        'attempted_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SaasSubscription::class, 'saas_subscription_id');
    }
}
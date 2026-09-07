<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Company -> Stripe Customer mapping for Custom Project Billing only. Kept
 * separate from SaasBillingCustomer so the two billing domains stay independent.
 */
class ProjectBillingCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'stripe_customer_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

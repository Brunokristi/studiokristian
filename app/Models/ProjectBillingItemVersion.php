<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBillingItemVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_billing_item_id',
        'starts_at',
        'ends_at',
        'unit_amount',
        'quantity',
        'interval',
        'interval_count',
        'stripe_price_id',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
        'unit_amount' => 'integer',
        'quantity' => 'integer',
        'interval_count' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProjectBillingItem::class, 'project_billing_item_id');
    }
}

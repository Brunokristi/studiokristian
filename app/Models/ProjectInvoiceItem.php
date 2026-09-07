<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_invoice_id',
        'project_billing_item_id',
        'name',
        'description',
        'quantity',
        'unit_amount',
        'tax_rate',
        'amount',
        'stripe_invoice_item_id',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_amount' => 'integer',
        'amount' => 'integer',
        'sort_order' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ProjectInvoice::class, 'project_invoice_id');
    }

    public function billingItem(): BelongsTo
    {
        return $this->belongsTo(ProjectBillingItem::class, 'project_billing_item_id');
    }
}

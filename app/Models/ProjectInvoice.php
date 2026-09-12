<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The business-facing invoice. StudioKristian owns the number, layout and PDF;
 * Stripe only collects the money.
 */
class ProjectInvoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOID = 'void';
    public const STATUS_UNCOLLECTIBLE = 'uncollectible';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';

    public const METHOD_STRIPE_CARD = 'stripe_card';
    public const METHOD_STRIPE_HOSTED = 'stripe_hosted';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    public const TAX_MODE_NONE = 'none';
    public const TAX_MODE_STANDARD = 'standard';
    public const TAX_MODE_REVERSE_CHARGE = 'reverse_charge';
    public const TAX_MODE_EXEMPT = 'exempt';

    protected $fillable = [
        'project_id',
        'company_id',
        'project_subscription_id',
        'invoice_number',
        'variable_symbol',
        'status',
        'payment_status',
        'payment_method',
        'collection_method',
        'stripe_invoice_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_payment_intent_id',
        'hosted_invoice_url',
        'currency',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'tax_mode',
        'total',
        'amount_paid',
        'amount_due',
        'stripe_fee_amount',
        'stripe_net_amount',
        'issue_date',
        'delivery_date',
        'due_date',
        'paid_at',
        'sent_at',
        'payment_failed_at',
        'voided_at',
        'supplier_name',
        'supplier_registration_number',
        'supplier_tax_number',
        'supplier_vat_number',
        'supplier_address',
        'supplier_iban',
        'customer_name',
        'customer_registration_number',
        'customer_tax_number',
        'customer_vat_number',
        'customer_address',
        'customer_email',
        'pdf_path',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'integer',
        'tax_amount' => 'integer',
        'total' => 'integer',
        'amount_paid' => 'integer',
        'amount_due' => 'integer',
        'stripe_fee_amount' => 'integer',
        'stripe_net_amount' => 'integer',
        'issue_date' => 'date',
        'delivery_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'sent_at' => 'datetime',
        'payment_failed_at' => 'datetime',
        'voided_at' => 'datetime',
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
        return $this->belongsTo(ProjectSubscription::class, 'project_subscription_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectInvoiceItem::class)->orderBy('sort_order');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(ProjectBillingAdjustment::class);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OPEN
            && $this->due_date !== null
            && $this->due_date->endOfDay()->isPast();
    }
}

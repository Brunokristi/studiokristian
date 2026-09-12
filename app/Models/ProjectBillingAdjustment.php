<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBillingAdjustment extends Model
{
    use HasFactory;

    public const TYPE_REFUND = 'refund';
    public const TYPE_CREDIT = 'credit';
    public const TYPE_DEBIT_NOTE = 'debit_note';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'project_id', 'company_id', 'project_invoice_id', 'project_subscription_id',
        'type', 'status', 'amount', 'currency', 'reason', 'stripe_refund_id',
        'stripe_credit_note_id', 'stripe_invoice_id', 'stripe_charge_id', 'processed_at', 'error_message',
    ];

    protected $casts = [
        'amount' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(ProjectInvoice::class, 'project_invoice_id'); }
    public function subscription(): BelongsTo { return $this->belongsTo(ProjectSubscription::class, 'project_subscription_id'); }
}

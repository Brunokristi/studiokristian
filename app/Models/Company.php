<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'registration_number', 'tax_number', 'vat_number', 'address', 'status',
        'internal_notes', 'archived_at', 'stripe_customer_id',
        'billing_email', 'billing_phone', 'billing_address_line1', 'billing_address_line2',
        'billing_address_city', 'billing_address_postal_code', 'billing_address_country',
    ];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function storageFolders(): HasMany
    {
        return $this->hasMany(CompanyStorageFolder::class);
    }

    public function saasSubscriptions(): HasMany
    {
        return $this->hasMany(SaasSubscription::class);
    }

    public function companyTrials(): HasMany
    {
        return $this->hasMany(CompanyTrial::class);
    }

    public function billingApiCredentials(): HasMany
    {
        return $this->hasMany(SaasCustomerApiCredential::class);
    }

    public function billingCustomers(): HasMany
    {
        return $this->hasMany(SaasBillingCustomer::class);
    }

    public function saasInvoices(): HasMany
    {
        return $this->hasMany(SaasInvoice::class);
    }

    public function saasPayments(): HasMany
    {
        return $this->hasMany(SaasPayment::class);
    }

    public function getDisplayLabelAttribute(): string
    {
        return $this->name;
    }
}
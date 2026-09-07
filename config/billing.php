<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Custom Project Billing
    |--------------------------------------------------------------------------
    |
    | Configuration for billing StudioKristian's own clients for custom
    | projects. This is a separate billing domain from SaaS billing and shares
    | nothing with it besides the Stripe account credentials.
    |
    */

    // Tags every Stripe object created by this domain so SaaS billing sync ignores them.
    'domain_tag' => 'custom_project',

    'currency' => env('BILLING_CURRENCY', 'EUR'),

    'invoice' => [
        // Sequential per year, e.g. 2026001. Payment method never affects the sequence.
        'number_padding' => 3,
        'due_days' => (int) env('BILLING_INVOICE_DUE_DAYS', 14),
        'pdf_disk' => env('BILLING_INVOICE_PDF_DISK', 'local'),
        'pdf_path' => 'billing/invoices',
        'footer_text' => env('BILLING_INVOICE_FOOTER'),
    ],

    /*
    | The business is currently NOT a VAT payer, so tax defaults to 0 and
    | invoices carry the legal note below. Becoming VAT registered later only
    | requires flipping `vat_payer` and setting `default_tax_rate`.
    */
    'tax' => [
        'vat_payer' => (bool) env('BILLING_VAT_PAYER', false),
        'default_tax_rate' => (float) env('BILLING_DEFAULT_TAX_RATE', 0),
        'not_vat_payer_note' => env(
            'BILLING_NOT_VAT_PAYER_NOTE',
            'Nie sme platiteľmi DPH.'
        ),
        'reverse_charge_note' => env(
            'BILLING_REVERSE_CHARGE_NOTE',
            'Prenesenie daňovej povinnosti podľa §69 ods. 12 zákona o DPH.'
        ),
    ],

    // Supplier (StudioKristian) details printed on every invoice PDF.
    'supplier' => [
        'name' => env('BILLING_SUPPLIER_NAME', 'Studio Kristian s.r.o.'),
        'registration_number' => env('BILLING_SUPPLIER_ICO'),
        'tax_number' => env('BILLING_SUPPLIER_DIC'),
        'vat_number' => env('BILLING_SUPPLIER_IC_DPH'),
        'address_line1' => env('BILLING_SUPPLIER_ADDRESS_LINE1'),
        'address_line2' => env('BILLING_SUPPLIER_ADDRESS_LINE2'),
        'city' => env('BILLING_SUPPLIER_CITY'),
        'postal_code' => env('BILLING_SUPPLIER_POSTAL_CODE'),
        'country' => env('BILLING_SUPPLIER_COUNTRY', 'Slovensko'),
        'email' => env('BILLING_SUPPLIER_EMAIL'),
        'phone' => env('BILLING_SUPPLIER_PHONE'),
        'iban' => env('BILLING_SUPPLIER_IBAN'),
        'swift' => env('BILLING_SUPPLIER_SWIFT'),
        'bank_name' => env('BILLING_SUPPLIER_BANK_NAME'),
        'register_note' => env('BILLING_SUPPLIER_REGISTER_NOTE'),
    ],
];

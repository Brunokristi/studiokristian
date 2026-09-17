@php
    $currencySymbol = static function ($currency) {
        return match (strtoupper((string) $currency)) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'CZK' => 'Kč',
            default => strtoupper((string) $currency),
        };
    };

    $money = static function ($amount, $currency) use ($currencySymbol) {
        $formatted = number_format(
            ((int) $amount) / 100,
            2,
            '.',
            ' '
        );

        $symbol = $currencySymbol(
            $currency
        );

        return match (
            strtoupper((string) $currency)
        ) {
            'EUR',
            'USD',
            'GBP' => $symbol.$formatted,

            default => $formatted.' '.$symbol,
        };
    };

    $formatIban = static function (?string $iban) {
        if (! $iban) {
            return null;
        }

        $iban = strtoupper(
            preg_replace(
                '/\s+/',
                '',
                $iban
            )
        );

        return trim(
            chunk_split(
                $iban,
                4,
                ' '
            )
        );
    };

    $supplier = config(
        'billing.supplier',
        []
    );

    $supplierName =
        $invoice->supplier_name
        ?: ($supplier['name'] ?? '');

    $supplierSwift =
        $supplier['swift'] ?? null;

    $registerNote =
        $supplier['register_note'] ?? null;

    $isVatPayer =
        (bool) ($tax['vat_payer'] ?? false);

    $brandBlue = '#133EB4';

    $isPreview =
        strtoupper(
            (string) $invoice->invoice_number
        ) === 'PREVIEW';

    $paymentMethodLabel =
        $invoice->supplier_iban
            ? 'Prevodom'
            : match ($invoice->payment_method) {
                \App\Models\ProjectInvoice::METHOD_BANK_TRANSFER =>
                    'Prevodom',

                \App\Models\ProjectInvoice::METHOD_STRIPE_CARD =>
                    'Platobná karta',

                default =>
                    'Online platba',
            };
@endphp

<!DOCTYPE html>

<html lang="sk">

<head>

    <meta charset="utf-8">

    <style>

        /*
        |--------------------------------------------------------------------------
        | Fonts
        |--------------------------------------------------------------------------
        */

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 400;
            src: url('{{ public_path('fonts/Inter/static/Inter_18pt-Regular.ttf') }}')
                format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 500;
            src: url('{{ public_path('fonts/Inter/static/Inter_18pt-Medium.ttf') }}')
                format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 600;
            src: url('{{ public_path('fonts/Inter/static/Inter_18pt-SemiBold.ttf') }}')
                format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 700;
            src: url('{{ public_path('fonts/Inter/static/Inter_18pt-Bold.ttf') }}')
                format('truetype');
        }

        @font-face {
            font-family: 'Space Mono';
            font-style: normal;
            font-weight: 400;
            src: url('{{ public_path('fonts/Space_Mono/SpaceMono-Regular.ttf') }}')
                format('truetype');
        }

        @font-face {
            font-family: 'Space Mono';
            font-style: normal;
            font-weight: 700;
            src: url('{{ public_path('fonts/Space_Mono/SpaceMono-Bold.ttf') }}')
                format('truetype');
        }


        /*
        |--------------------------------------------------------------------------
        | A4
        |--------------------------------------------------------------------------
        */

        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 595.28pt;
            height: 841.89pt;

            margin: 0;
            padding: 0;

            color: #000000;

            font-family:
                'Inter',
                sans-serif;

            font-size: 9px;
            line-height: 1.4;
        }

        body {
            position: relative;
        }


        /*
        |--------------------------------------------------------------------------
        | Printable area
        |--------------------------------------------------------------------------
        */

        .invoice {
            position: absolute;

            top: 42pt;
            right: 50pt;
            left: 50pt;
        }

        .rule {
            width: 100%;
            height: 1px;

            margin: 0;

            background:
                {{ $brandBlue }};
        }

        .section-label {
            color:
                {{ $brandBlue }};

            font-family:
                'Space Mono',
                monospace;

            font-size: 7px;
            font-weight: 700;

            letter-spacing: 0.2px;

            text-transform: uppercase;
        }


        /*
        |--------------------------------------------------------------------------
        | Universal four-column grid
        |--------------------------------------------------------------------------
        */

        .grid-4 {
            width: 100%;

            border-collapse: collapse;
            table-layout: fixed;
        }

        .grid-4 col {
            width: 25%;
        }

        .grid-4 td {
            padding: 0;

            vertical-align: top;
        }

        .grid-row td {
            padding-top: 2px;
            padding-bottom: 2px;
        }

        .grid-label {
            padding-right: 12px !important;

            color: #777777;

            font-size: 7.5px;
            font-weight: 400;

            line-height: 1.35;
        }

        .grid-value {
            padding-right: 12px !important;

            color: #000000;

            font-size: 8px;
            font-weight: 400;

            line-height: 1.35;
        }

        .grid-value-strong {
            font-weight: 700;
        }


        /*
        |--------------------------------------------------------------------------
        | Center gutter
        |--------------------------------------------------------------------------
        */

        .grid-gap-left {
            padding-right: 36px !important;
        }

        .grid-gap-right {
            padding-left: 36px !important;
        }


        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .header {
            width: 100%;

            margin:
                0
                0
                30px
                0;

            border-collapse: collapse;
            table-layout: fixed;
        }

        .header td {
            padding: 0;

            vertical-align: top;
        }

        .header-title {
            width: 75%;
        }

        .header-date {
            width: 25%;

            text-align: right;
        }

        .invoice-title {
            margin: 0;

            color:
                {{ $brandBlue }};

            font-family:
                'Space Mono',
                monospace;

            font-size: 24px;
            font-weight: 700;

            line-height: 1;
        }

        .invoice-title-number {
            margin-left: 6px;
        }

        .document-meta {
            color: #777777;

            font-family:
                'Space Mono',
                monospace;

            font-size: 7px;
        }


        /*
        |--------------------------------------------------------------------------
        | Parties
        |--------------------------------------------------------------------------
        */

        .parties-section {
            padding-bottom: 18px;
        }

        .party-headings td {
            padding-bottom: 11px;
        }

        .party-names td {
            padding-bottom: 9px;
        }

        .party-name {
            font-size: 10px;
            font-weight: 600;

            line-height: 1.25;
        }

        .party-grid .grid-label {
            text-transform: uppercase !important;
        }

        .register-row td {
            padding-top: 5px;
        }

        .register-value {
            color: #777777;

            font-size: 6.5px;

            line-height: 1.35;
        }


        /*
        |--------------------------------------------------------------------------
        | Payment details
        |--------------------------------------------------------------------------
        */

        .payment-section {
            padding:
                12px
                0;
        }

        .payment-heading {
            margin-bottom: 9px;
        }

        .payment-grid {
            width: 100%;

            border-collapse: collapse;
            table-layout: fixed;
        }

        .payment-grid col {
            width: 25%;
        }


        /*
        |--------------------------------------------------------------------------
        | Invoice copy
        |--------------------------------------------------------------------------
        */

        .invoice-copy {
            padding:
                10px
                0
                11px
                0;
        }

        .invoice-copy p {
            margin:
                0
                0
                3px
                0;
        }

        .invoice-copy .main-copy {
            font-size: 8px;

            line-height: 1.4;
        }

        .invoice-copy .legal-copy {
            color: #666666;

            font-size: 7.5px;

            line-height: 1.4;
        }


        /*
        |--------------------------------------------------------------------------
        | Items
        |--------------------------------------------------------------------------
        */

        .items {
            width: 100%;

            border-collapse: collapse;
            table-layout: fixed;
        }

        .items th {
            padding:
                5px
                6px;

            color: #000000;

            font-family:
                'Space Mono',
                monospace;

            font-size: 6px;
            font-weight: 700;

            text-align: left;
            text-transform: uppercase;
        }

        .items td {
            padding: 6px;

            vertical-align: top;

            font-size: 8px;
        }

        .items tbody tr:nth-child(odd) {
            background: #F0F3FB;
        }

        .items .description-column {
            width: 43%;
        }

        .items .quantity-column {
            width: 11%;

            text-align: center;
        }

        .items .price-column {
            width: 21%;

            text-align: right;
        }

        .items .tax-column {
            width: 9%;

            text-align: right;
        }

        .items .total-column {
            width: 25%;

            text-align: right;
        }

        .item-name {
            font-weight: 500;
        }

        .item-description {
            margin-top: 2px;

            color: #777777;

            font-size: 7px;

            line-height: 1.35;
        }


        /*
        |--------------------------------------------------------------------------
        | Totals
        |--------------------------------------------------------------------------
        */

        .totals-wrap {
            width: 100%;

            margin-top: 14px;
        }

        .totals-grid {
            width: 100%;

            border-collapse: collapse;
            table-layout: fixed;
        }

        .totals-grid col {
            width: 25%;
        }

        .totals-grid td {
            padding:
                2px
                0;
        }

        .totals-label {
            color: #777777;

            font-size: 7.5px;
        }

        .totals-value {
            font-family:
                'Space Mono',
                monospace;

            font-size: 7.5px;

            text-align: right;
        }

        .grand-total-label,
        .grand-total-value {
            padding-top: 7px !important;

            border-top:
                1px solid
                {{ $brandBlue }};
        }

        .grand-total-label {
            color: #000000;

            font-family:
                'Space Mono',
                monospace;

            font-size: 7.5px;
            font-weight: 700;

            text-transform: uppercase;
        }

        .grand-total-value {
            color: #000000;

            font-family:
                'Space Mono',
                monospace;

            font-size: 16px;
            font-weight: 700;

            line-height: 1;

            text-align: right;
        }


        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        .footer {
            position: absolute;

            right: 50pt;
            bottom: 25pt;
            left: 50pt;
        }

        /*
        |--------------------------------------------------------------------------
        | Footer payment
        |--------------------------------------------------------------------------
        */

        .footer-payment {
            width: 100%;

            padding-bottom: 16px;

            border-bottom:
                1px solid
                {{ $brandBlue }};
        }

        .footer-payment-table {
            width: 100%;

            border-collapse: collapse;
            table-layout: fixed;
        }

        .footer-payment-table col {
            width: 25%;
        }

        .footer-payment-table td {
            padding: 0;

            vertical-align: top;
        }


        /*
        |--------------------------------------------------------------------------
        | Footer payment heading
        |--------------------------------------------------------------------------
        */

        .footer-payment-heading-row td {
            padding-bottom: 11px;
        }

        .footer-payment-heading {
            color:
                {{ $brandBlue }};

            font-family:
                'Space Mono',
                monospace;

            font-size: 7px;
            font-weight: 700;

            letter-spacing: 0.2px;

            text-transform: uppercase;
        }


        /*
        |--------------------------------------------------------------------------
        | Footer payment content
        |--------------------------------------------------------------------------
        */

        .footer-payment-left {
            padding-right: 36px !important;
        }

        .footer-payment-right {
            padding-left: 36px !important;
        }

        .footer-payment-title {
            margin-bottom: 5px;

            color: #000000;

            font-size: 10px;
            font-weight: 600;

            line-height: 1.25;
        }

        .footer-payment-text {
            margin-bottom: 8px;

            color: #777777;

            font-size: 7.5px;
            font-weight: 400;

            line-height: 1.45;
        }


        /*
        |--------------------------------------------------------------------------
        | QR
        |--------------------------------------------------------------------------
        */

        .qr-wrap {
            width: 100%;
        }

        .qr-image {
            display: block;

            width: 72px;
            height: 72px;

            margin: 0;
            padding: 0;
        }


        /*
        |--------------------------------------------------------------------------
        | Online payment
        |--------------------------------------------------------------------------
        */

        .payment-button {
            display: inline-block;

            padding:
                8px
                12px;

            background:
                {{ $brandBlue }};

            color: #FFFFFF !important;

            font-family:
                'Inter',
                sans-serif;

            font-size: 7px;
            font-weight: 600;

            line-height: 1;

            text-decoration: none;

            border-radius: 2px;
        }


        /*
        |--------------------------------------------------------------------------
        | Footer brand
        |--------------------------------------------------------------------------
        */

        .footer-brand {
            padding-top: 14px;

            text-align: center;
        }

        .footer-logo {
            width: auto;
            height: 10px;
        }

        .footer-text {
            margin-top: 3px;

            color: #8A8A8A;

            font-family:
                'Space Mono',
                monospace;

            font-size: 5.5px;
        }

    </style>

</head>

<body>


    <div class="invoice">


        {{-- Header --}}

        <table class="header">

            <tbody>

                <tr>

                    <td class="header-title">

                        <div class="invoice-title">
                            FAKTÚRA<span class="invoice-title-number">{{ $invoice->invoice_number }}</span>
                        </div>

                    </td>

                </tr>

            </tbody>

        </table>


        {{-- Supplier / customer --}}

        <div class="parties-section">

            <table class="grid-4 party-grid">

                <colgroup>
                    <col>
                    <col>
                    <col>
                    <col>
                </colgroup>

                <tbody>


                    {{-- Section headings --}}

                    <tr class="party-headings">

                        <td colspan="2">

                            <div class="section-label">
                                Dodávateľ
                            </div>

                        </td>

                        <td
                            colspan="2"
                            class="grid-gap-right"
                        >

                            <div class="section-label">
                                Odberateľ
                            </div>

                        </td>

                    </tr>


                    {{-- Company names --}}

                    <tr class="party-names">

                        <td colspan="2">

                            <div class="party-name">
                                {{ $supplierName }}
                            </div>

                        </td>

                        <td
                            colspan="2"
                            class="grid-gap-right"
                        >

                            <div class="party-name">
                                {{ $invoice->customer_name }}
                            </div>

                        </td>

                    </tr>


                    {{-- IČO --}}

                    <tr class="grid-row">

                        <td class="grid-label">
                            IČO
                        </td>

                        <td class="grid-value grid-gap-left">
                            {{ $invoice->supplier_registration_number }}
                        </td>

                        <td class="grid-label grid-gap-right">
                            IČO
                        </td>

                        <td class="grid-value">
                            {{ $invoice->customer_registration_number }}
                        </td>

                    </tr>


                    {{-- DIČ --}}

                    <tr class="grid-row">

                        <td class="grid-label">
                            DIČ
                        </td>

                        <td class="grid-value grid-gap-left">
                            {{ $invoice->supplier_tax_number }}
                        </td>

                        <td class="grid-label grid-gap-right">
                            DIČ
                        </td>

                        <td class="grid-value">
                            {{ $invoice->customer_tax_number }}
                        </td>

                    </tr>


                    {{-- IČ DPH --}}

                    <tr class="grid-row">

                        <td class="grid-label">
                            IČ DPH
                        </td>

                        <td class="grid-value grid-gap-left">
                            {{ $invoice->supplier_vat_number ?: '—' }}
                        </td>

                        <td class="grid-label grid-gap-right">
                            IČ DPH
                        </td>

                        <td class="grid-value">
                            {{ $invoice->customer_vat_number ?: '—' }}
                        </td>

                    </tr>


                    {{-- Address --}}

                    <tr class="grid-row">

                        <td class="grid-label">
                            Adresa
                        </td>

                        <td class="grid-value grid-gap-left">

                            @if ($invoice->supplier_address)

                                {!! nl2br(
                                    e(
                                        $invoice->supplier_address
                                    )
                                ) !!}

                            @else

                                —

                            @endif

                        </td>

                        <td class="grid-label grid-gap-right">
                            Adresa
                        </td>

                        <td class="grid-value">

                            @if ($invoice->customer_address)

                                {!! nl2br(
                                    e(
                                        $invoice->customer_address
                                    )
                                ) !!}

                            @else

                                —

                            @endif

                        </td>

                    </tr>


                    {{-- Register note --}}

                    @if ($registerNote)

                        <tr class="register-row">

                            <td class="grid-label">
                                Register
                            </td>

                            <td
                                colspan="3"
                                class="register-value"
                            >
                                {{ $registerNote }}
                            </td>

                        </tr>

                    @endif


                </tbody>

            </table>

        </div>


        <div class="rule"></div>


        {{-- Payment details + dates --}}

        <div class="payment-section">

            <div class="payment-heading section-label">
                Platobné údaje
            </div>

            <table class="grid-4 payment-grid">

                <colgroup>
                    <col>
                    <col>
                    <col>
                    <col>
                </colgroup>

                <tbody>


                    {{-- IBAN / Issue date --}}

                    <tr class="grid-row">

                        <td class="grid-label">
                            IBAN
                        </td>

                        <td class="grid-value grid-gap-left">

                            @if ($invoice->supplier_iban)

                                {{ $formatIban($invoice->supplier_iban) }}

                            @else

                                —

                            @endif

                        </td>

                        <td class="grid-label grid-gap-right">
                            Dátum vystavenia
                        </td>

                        <td class="grid-value">
                            {{ $invoice->issue_date?->format('d. m. Y') }}
                        </td>

                    </tr>


                    {{-- SWIFT / Delivery date --}}

                    <tr class="grid-row">

                        <td class="grid-label">
                            SWIFT / BIC
                        </td>

                        <td class="grid-value grid-gap-left">
                            {{ $supplierSwift ?: '—' }}
                        </td>

                        <td class="grid-label grid-gap-right">
                            Dátum dodania
                        </td>

                        <td class="grid-value">
                            {{ $invoice->delivery_date?->format('d. m. Y') }}
                        </td>

                    </tr>


                    {{-- Method / Due date --}}

                    <tr class="grid-row">

                        <td class="grid-label">
                            Forma úhrady
                        </td>

                        <td class="grid-value grid-gap-left">
                            {{ $paymentMethodLabel }}
                        </td>

                        <td class="grid-label grid-gap-right">
                            Dátum splatnosti
                        </td>

                        <td class="grid-value">
                            {{ $invoice->due_date?->format('d. m. Y') }}
                        </td>

                    </tr>


                    {{-- Variable symbol --}}

                    @if (! $isPreview)

                        <tr class="grid-row">

                            <td class="grid-label">
                                Variabilný symbol
                            </td>

                            <td class="grid-value grid-gap-left">
                                {{
                                    $invoice->variable_symbol
                                    ?: $invoice->invoice_number
                                }}
                            </td>

                            <td></td>

                            <td></td>

                        </tr>

                    @endif


                </tbody>

            </table>

        </div>


        <div class="rule"></div>


        {{-- Invoice copy --}}

        <div class="invoice-copy">

            @if ($invoice->project?->name)

                <p class="main-copy">
                    Fakturujeme Vám za poskytnuté služby projektu
                    {{ $invoice->project->name }}.
                </p>

            @endif


            @if (
                $invoice->tax_mode ===
                \App\Models\ProjectInvoice::TAX_MODE_REVERSE_CHARGE
            )

                <p class="main-copy">
                    {{ $tax['reverse_charge_note'] ?? '' }}
                </p>

            @elseif (! $isVatPayer)

                <p class="main-copy">
                    {{
                        $tax['not_vat_payer_note']
                        ?? ($supplierName.' nie je platcom DPH.')
                    }}
                </p>

            @endif


            @if ($invoice->notes)

                <p class="main-copy">

                    {!! nl2br(
                        e(
                            $invoice->notes
                        )
                    ) !!}

                </p>

            @endif

        </div>


        {{-- Items --}}

        <table class="items">

            <thead>

                <tr>

                    <th class="description-column">
                        Popis
                    </th>

                    <th class="quantity-column">
                        Počet
                    </th>

                    <th class="price-column">
                        Jednotková cena
                    </th>

                    @if ($invoice->tax_rate > 0)

                        <th class="tax-column">
                            DPH
                        </th>

                    @endif

                    <th class="total-column">
                        Celkom
                    </th>

                </tr>

            </thead>


            <tbody>

                @foreach ($items as $item)

                    <tr>

                        <td class="description-column">

                            <div class="item-name">
                                {{ $item->name }}
                            </div>

                            @if ($item->description)

                                <div class="item-description">
                                    {{ $item->description }}
                                </div>

                            @endif

                        </td>


                        <td class="quantity-column">
                            {{ $item->quantity }}
                        </td>


                        <td class="price-column">

                            {{
                                $money(
                                    $item->unit_amount,
                                    $invoice->currency
                                )
                            }}

                        </td>


                        @if ($invoice->tax_rate > 0)

                            <td class="tax-column">

                                {{
                                    rtrim(
                                        rtrim(
                                            number_format(
                                                (float) $item->tax_rate,
                                                2,
                                                ',',
                                                ' '
                                            ),
                                            '0'
                                        ),
                                        ','
                                    )
                                }} %

                            </td>

                        @endif


                        <td class="total-column">

                            {{
                                $money(
                                    $item->amount,
                                    $invoice->currency
                                )
                            }}

                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>


        {{-- Totals --}}

        <div class="totals-wrap">

            <table class="totals-grid">

                <colgroup>
                    <col>
                    <col>
                    <col>
                    <col>
                </colgroup>

                <tbody>


                    @if ($invoice->tax_amount > 0)

                        <tr>

                            <td></td>

                            <td></td>

                            <td class="totals-label">
                                Základ
                            </td>

                            <td class="totals-value">

                                {{
                                    $money(
                                        $invoice->subtotal,
                                        $invoice->currency
                                    )
                                }}

                            </td>

                        </tr>


                        <tr>

                            <td></td>

                            <td></td>

                            <td class="totals-label">
                                DPH
                            </td>

                            <td class="totals-value">

                                {{
                                    $money(
                                        $invoice->tax_amount,
                                        $invoice->currency
                                    )
                                }}

                            </td>

                        </tr>

                    @endif


                    <tr>

                        <td></td>

                        <td></td>

                        <td class="grand-total-value">

                            {{
                                $money(
                                    $invoice->total,
                                    $invoice->currency
                                )
                            }}

                        </td>

                    </tr>


                </tbody>

            </table>

        </div>


    </div>


    {{-- Footer --}}

    <footer class="footer">


        {{-- Payment options --}}

        @if (
            ($paymentQrDataUri ?? null)
            || (! $isPreview && ($paymentUrl ?? null))
        )

            <div class="footer-payment">

                <table class="footer-payment-table">

                    <colgroup>
                        <col>
                        <col>
                        <col>
                        <col>
                    </colgroup>

                    <tbody>


                        {{-- Section heading --}}

                        <tr class="footer-payment-heading-row">

                            <td colspan="4">

                                <div class="footer-payment-heading">
                                    Úhrada faktúry
                                </div>

                            </td>

                        </tr>


                        {{-- Payment methods --}}

                        <tr>


                            {{-- PAY by square --}}

                            <td
                                colspan="2"
                                class="footer-payment-left"
                            >

                                @if ($paymentQrDataUri ?? null)


                                    <div class="qr-wrap">

                                        <img
                                            src="{{ $paymentQrDataUri }}"
                                            alt="PAY by square"
                                            class="qr-image"
                                        >

                                    </div>

                                @endif

                            </td>


                            {{-- Online payment --}}

                            <td
                                colspan="2"
                                class="footer-payment-right"
                            >

                                @if (
                                    ! $isPreview
                                    && ($paymentUrl ?? null)
                                )

                                    <div class="footer-payment-title">
                                        Online platba
                                    </div>

                                    <div class="footer-payment-text">
                                        Faktúru môžete uhradiť
                                        bezpečne online platbou.
                                    </div>

                                    <a
                                        href="{{ $paymentUrl }}"
                                        class="payment-button"
                                    >
                                        Zaplatiť online
                                    </a>

                                @endif

                            </td>


                        </tr>


                    </tbody>

                </table>

            </div>

        @endif


        {{-- Brand --}}

        <div class="footer-brand">

            <img
                src="{{ public_path('assets/logo.png') }}"
                alt="studio kristian"
                class="footer-logo"
            >

            @if ($footerText)

                <div class="footer-text">
                    {{ $footerText }}
                </div>

            @endif

        </div>


    </footer>


</body>

</html>
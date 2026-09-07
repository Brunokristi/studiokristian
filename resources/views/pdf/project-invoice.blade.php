@php
    $money = static fn ($amount, $currency) => number_format(((int) $amount) / 100, 2, ',', ' ').' '.$currency;
    $supplier = config('billing.supplier');
@endphp
    <!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111827; margin: 0; }
        .header { width: 100%; margin-bottom: 24px; }
        .title { font-size: 26px; font-weight: bold; letter-spacing: 1px; }
        .muted { color: #6b7280; }
        .parties { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .parties td { width: 50%; vertical-align: top; padding: 10px 12px; border: 1px solid #e5e7eb; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #6b7280; margin-bottom: 6px; }
        .party-name { font-size: 13px; font-weight: bold; margin-bottom: 4px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .meta td { padding: 4px 0; }
        .meta .k { color: #6b7280; width: 45%; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th { text-align: left; font-size: 10px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #d1d5db; padding: 8px 6px; }
        table.items td { padding: 8px 6px; border-bottom: 1px solid #f3f4f6; }
        .right { text-align: right; }
        .totals { width: 45%; margin-left: auto; margin-top: 14px; border-collapse: collapse; }
        .totals td { padding: 6px; }
        .totals .grand { font-size: 14px; font-weight: bold; border-top: 2px solid #111827; }
        .footer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #6b7280; }
        .note { margin-top: 14px; font-size: 10px; }
    </style>
</head>
<body>

<table class="header">
    <tr>
        <td>
            <div class="title">FAKTÚRA</div>
            <div class="muted">{{ $invoice->invoice_number }}</div>
        </td>
        <td class="right">
            <div class="party-name">{{ $invoice->supplier_name ?: ($supplier['name'] ?? '') }}</div>
            @if ($supplier['email'] ?? null)
                <div class="muted">{{ $supplier['email'] }}</div>
            @endif
            @if ($supplier['phone'] ?? null)
                <div class="muted">{{ $supplier['phone'] }}</div>
            @endif
        </td>
    </tr>
</table>

<table class="parties">
    <tr>
        <td>
            <div class="label">Dodávateľ</div>
            <div class="party-name">{{ $invoice->supplier_name }}</div>
            <div>{!! nl2br(e($invoice->supplier_address)) !!}</div>
            @if ($invoice->supplier_registration_number)
                <div>IČO: {{ $invoice->supplier_registration_number }}</div>
            @endif
            @if ($invoice->supplier_tax_number)
                <div>DIČ: {{ $invoice->supplier_tax_number }}</div>
            @endif
            @if ($invoice->supplier_vat_number)
                <div>IČ DPH: {{ $invoice->supplier_vat_number }}</div>
            @endif
            @if ($supplier['register_note'] ?? null)
                <div class="muted">{{ $supplier['register_note'] }}</div>
            @endif
        </td>
        <td>
            <div class="label">Odberateľ</div>
            <div class="party-name">{{ $invoice->customer_name }}</div>
            <div>{!! nl2br(e($invoice->customer_address)) !!}</div>
            @if ($invoice->customer_registration_number)
                <div>IČO: {{ $invoice->customer_registration_number }}</div>
            @endif
            @if ($invoice->customer_tax_number)
                <div>DIČ: {{ $invoice->customer_tax_number }}</div>
            @endif
            @if ($invoice->customer_vat_number)
                <div>IČ DPH: {{ $invoice->customer_vat_number }}</div>
            @endif
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td>
            <table class="meta">
                <tr>
                    <td class="k">Dátum vystavenia</td>
                    <td>{{ $invoice->issue_date?->format('d.m.Y') }}</td>
                </tr>
                <tr>
                    <td class="k">Dátum dodania</td>
                    <td>{{ $invoice->delivery_date?->format('d.m.Y') }}</td>
                </tr>
                <tr>
                    <td class="k">Dátum splatnosti</td>
                    <td>{{ $invoice->due_date?->format('d.m.Y') }}</td>
                </tr>
            </table>
        </td>
        <td>
            <table class="meta">
                <tr>
                    <td class="k">Variabilný symbol</td>
                    <td>{{ $invoice->variable_symbol ?: $invoice->invoice_number }}</td>
                </tr>
                <tr>
                    <td class="k">Forma úhrady</td>
                    <td>
                        @switch($invoice->payment_method)
                            @case(\App\Models\ProjectInvoice::METHOD_BANK_TRANSFER) Bankový prevod @break
                            @case(\App\Models\ProjectInvoice::METHOD_STRIPE_CARD) Platobná karta @break
                            @default Online platba
                        @endswitch
                    </td>
                </tr>
                @if ($invoice->supplier_iban)
                    <tr>
                        <td class="k">IBAN</td>
                        <td>{{ $invoice->supplier_iban }}</td>
                    </tr>
                @endif
                @if ($supplier['swift'] ?? null)
                    <tr>
                        <td class="k">SWIFT</td>
                        <td>{{ $supplier['swift'] }}</td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
    <tr>
        <th>Položka</th>
        <th class="right">Množstvo</th>
        <th class="right">Jedn. cena</th>
        @if ($invoice->tax_rate > 0)
            <th class="right">DPH</th>
        @endif
        <th class="right">Spolu</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($items as $item)
        <tr>
            <td>
                <strong>{{ $item->name }}</strong>
                @if ($item->description)
                    <div class="muted">{{ $item->description }}</div>
                @endif
            </td>
            <td class="right">{{ $item->quantity }}</td>
            <td class="right">{{ $money($item->unit_amount, $invoice->currency) }}</td>
            @if ($invoice->tax_rate > 0)
                <td class="right">{{ rtrim(rtrim(number_format((float) $item->tax_rate, 2, ',', ' '), '0'), ',') }} %</td>
            @endif
            <td class="right">{{ $money($item->amount, $invoice->currency) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr>
        <td class="k">Základ</td>
        <td class="right">{{ $money($invoice->subtotal, $invoice->currency) }}</td>
    </tr>
    @if ($invoice->tax_amount > 0)
        <tr>
            <td class="k">DPH</td>
            <td class="right">{{ $money($invoice->tax_amount, $invoice->currency) }}</td>
        </tr>
    @endif
    <tr class="grand">
        <td>Celkom na úhradu</td>
        <td class="right">{{ $money($invoice->total, $invoice->currency) }}</td>
    </tr>
</table>

<div class="note">
    @if ($invoice->tax_mode === \App\Models\ProjectInvoice::TAX_MODE_REVERSE_CHARGE)
        {{ $tax['reverse_charge_note'] }}
    @elseif (! ($tax['vat_payer'] ?? false))
        {{ $tax['not_vat_payer_note'] }}
    @endif
</div>

@if ($invoice->notes)
    <div class="note">{!! nl2br(e($invoice->notes)) !!}</div>
@endif

<div class="footer">
    {{ $footerText ?: 'Ďakujeme za spoluprácu.' }}
</div>

</body>
</html>

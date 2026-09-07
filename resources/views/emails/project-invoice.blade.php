@extends('emails.layout')


@section('content')
    <table
        role="presentation"
        width="100%"
        cellspacing="0"
        cellpadding="0"
        border="0"
        style="
            width: 100%;
            border-collapse: collapse;
        "
    >
        <tr>
            <td align="center">
                <h1
                    style="
                        margin: 0 0 24px 0;
                        color: #000000;
                        font-family: 'Space Mono', 'Courier New', Courier, monospace;
                        font-size: 30px;
                        font-weight: 700;
                        line-height: 1.2;
                        letter-spacing: -0.03em;
                        text-transform: uppercase;
                    "
                >
                    {{ $heading }}
                </h1>


                <p
                    style="
                        margin: 0 auto 36px auto;
                        max-width: 460px;
                        color: #000000;
                        font-family: 'Inter', Arial, Helvetica, sans-serif;
                        font-size: 14px;
                        font-weight: 300;
                        line-height: 1.6;
                        text-transform: uppercase;
                    "
                >
                    {{ $intro }}
                </p>


                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="
                        width: 100%;
                        max-width: 520px;
                        margin: 0 auto 32px auto;
                        border-collapse: collapse;
                        border: 1px solid #000000;
                    "
                >
                    @foreach ([
                        'Faktúra' => $invoice->invoice_number,
                        'Projekt' => $invoice->project?->name,
                        'Dátum vystavenia' => $invoice->issue_date?->format('d.m.Y'),
                        'Splatnosť' => $invoice->due_date?->format('d.m.Y'),
                        'Suma' => number_format(((int) $invoice->total) / 100, 2, ',', ' ').' '.$invoice->currency,
                    ] as $label => $value)
                        @if ($value)
                            <tr>
                                <td
                                    style="
                                        padding: 12px 16px;
                                        border-bottom: 1px solid #e5e5e5;
                                        color: #666666;
                                        font-family: 'Inter', Arial, Helvetica, sans-serif;
                                        font-size: 12px;
                                        text-transform: uppercase;
                                    "
                                >
                                    {{ $label }}
                                </td>
                                <td
                                    align="right"
                                    style="
                                        padding: 12px 16px;
                                        border-bottom: 1px solid #e5e5e5;
                                        color: #000000;
                                        font-family: 'Inter', Arial, Helvetica, sans-serif;
                                        font-size: 12px;
                                        font-weight: 700;
                                        text-transform: uppercase;
                                    "
                                >
                                    {{ $value }}
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </table>


                @if ($actionUrl)
                    <table
                        role="presentation"
                        cellspacing="0"
                        cellpadding="0"
                        border="0"
                        style="margin: 0 auto;"
                    >
                        <tr>
                            <td
                                align="center"
                                style="background-color: #000000;"
                            >
                                <a
                                    href="{{ $actionUrl }}"
                                    style="
                                        display: inline-block;
                                        padding: 14px 32px;
                                        color: #ffffff;
                                        font-family: 'Space Mono', 'Courier New', Courier, monospace;
                                        font-size: 13px;
                                        font-weight: 700;
                                        text-decoration: none;
                                        text-transform: uppercase;
                                        letter-spacing: 0.05em;
                                    "
                                >
                                    Zaplatiť online
                                </a>
                            </td>
                        </tr>
                    </table>
                @endif
            </td>
        </tr>
    </table>
@endsection

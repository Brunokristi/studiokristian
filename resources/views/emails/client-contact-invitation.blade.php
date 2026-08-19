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
                    You’re invited
                </h1>


                <p
                    style="
                        margin: 0 0 10px 0;
                        color: #000000;
                        font-family: 'Inter', Arial, Helvetica, sans-serif;
                        font-size: 14px;
                        font-weight: 300;
                        line-height: 1.6;
                        text-transform: uppercase;
                    "
                >
                    Hello{{ $recipientName ? ' '.$recipientName : '' }},
                </p>


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
                    You’ve been invited to access the client portal for
                    <strong style="font-weight: 700;">
                        {{ $companyName }}
                    </strong>.
                    Use the button below to open your portal and get started.
                </p>


                <!-- Button -->
                <table
                    role="presentation"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    align="center"
                    style="
                        margin: 0 auto;
                        border-collapse: collapse;
                    "
                >
                    <tr>
                        <td
                            align="center"
                            style="
                                border: 1px solid #000000;
                            "
                        >
                            <a
                                href="{{ $actionUrl }}"
                                style="
                                    display: inline-block;
                                    padding: 13px 22px;
                                    color: #000000;
                                    font-family: 'Space Mono', 'Courier New', Courier, monospace;
                                    font-size: 12px;
                                    font-weight: 700;
                                    line-height: 1.4;
                                    text-decoration: none;
                                    text-transform: uppercase;
                                "
                            >
                                Open client portal
                            </a>
                        </td>
                    </tr>
                </table>


                <p
                    style="
                        margin: 42px auto 0 auto;
                        max-width: 420px;
                        color: #777777;
                        font-family: 'Inter', Arial, Helvetica, sans-serif;
                        font-size: 11px;
                        font-weight: 300;
                        line-height: 1.6;
                        text-transform: uppercase;
                    "
                >
                    If you did not expect this invitation,
                    you can safely ignore this email.
                </p>
            </td>
        </tr>
    </table>
@endsection
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
                    New client ticket
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
                    <strong style="font-weight: 700;">
                        {{ $projectName }}
                    </strong>
                    received a new client support ticket.
                </p>


                <!-- Ticket -->
                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="
                        width: 100%;
                        max-width: 520px;
                        margin: 0 auto 36px auto;
                        border-collapse: collapse;
                        border-top: 1px solid #d8d6cf;
                    "
                >
                    <tr>
                        <td
                            style="
                                padding: 16px 0 12px 0;
                                border-bottom: 1px solid #d8d6cf;
                                color: #000000;
                                font-family: 'Space Mono', 'Courier New', Courier, monospace;
                                font-size: 14px;
                                font-weight: 700;
                                line-height: 1.5;
                                text-transform: uppercase;
                            "
                        >
                            {{ $ticketTitle }}
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="
                                padding: 16px 0 16px 0;
                                border-bottom: 1px solid #d8d6cf;
                                color: #777777;
                                font-family: 'Inter', Arial, Helvetica, sans-serif;
                                font-size: 12px;
                                font-weight: 300;
                                line-height: 1.6;
                            "
                        >
                            {{ $ticketDescription }}
                        </td>
                    </tr>
                </table>


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
                                Open ticket board
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
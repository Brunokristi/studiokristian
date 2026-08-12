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
                        margin: 0 0 20px 0;
                        color: #000000;
                        font-family: 'Space Mono', 'Courier New', Courier, monospace;
                        font-size: 30px;
                        font-weight: 700;
                        line-height: 1.2;
                        letter-spacing: -0.03em;
                        text-transform: uppercase;
                    "
                >
                    Sign in to your workspace
                </h1>


                <p
                    style="
                        margin: 0 0 10px 0;
                        color: #000000;
                        font-family: 'Inter', Arial, Helvetica, sans-serif;
                        font-size: 14px;
                        font-weight: 400;
                        line-height: 1.6;
                    "
                >
                    Hello {{ $recipientName }},
                </p>


                <p
                    style="
                        margin: 0 auto 36px auto;
                        max-width: 460px;
                        color: #000000;
                        font-family: 'Inter', Arial, Helvetica, sans-serif;
                        font-size: 14px;
                        font-weight: 400;
                        line-height: 1.6;
                    "
                >
                    Use the secure one-time link below to sign in to your workspace.
                    The link expires in 10 minutes.
                </p>


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
                        <td align="center">
                            <a
                                href="{{ $actionUrl }}"
                                style="
                                    display: inline-block;
                                    color: #000000;
                                    font-family: 'Space Mono', 'Courier New', Courier, monospace;
                                    font-size: 13px;
                                    font-weight: 700;
                                    line-height: 1.4;
                                    text-decoration: none;
                                    text-transform: lowercase;
                                "
                            >
                                <span
                                    style="
                                        display: inline-block;
                                        padding: 0 0 6px 0;
                                        border-bottom: 2px solid #000000;
                                    "
                                >
                                    sign in to workspace&nbsp;&nbsp;&rarr;
                                </span>
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
                        font-weight: 400;
                        line-height: 1.6;
                    "
                >
                    If you did not request this link, you can safely ignore this email.
                </p>
            </td>
        </tr>
    </table>
@endsection
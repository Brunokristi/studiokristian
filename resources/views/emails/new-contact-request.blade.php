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
                    New contact request
                </h1>


                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    align="center"
                    style="
                        margin: 0 auto;
                        max-width: 460px;
                        border-collapse: collapse;
                        text-align: left;
                    "
                >
                    <tr>
                        <td style="padding: 6px 0; color: #000000; font-family: 'Inter', Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 300; line-height: 1.6; text-transform: uppercase;">
                            <strong style="font-weight: 700;">Name:</strong> {{ $name }}
                        </td>
                    </tr>

                    @if ($service)
                        <tr>
                            <td style="padding: 6px 0; color: #000000; font-family: 'Inter', Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 300; line-height: 1.6; text-transform: uppercase;">
                                <strong style="font-weight: 700;">Service:</strong> {{ $service }}
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding: 6px 0; color: #000000; font-family: 'Inter', Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 300; line-height: 1.6; text-transform: uppercase;">
                            <strong style="font-weight: 700;">Preferred contact:</strong> {{ $contactMethod }}
                        </td>
                    </tr>

                    @if ($email)
                        <tr>
                            <td style="padding: 6px 0; color: #000000; font-family: 'Inter', Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 300; line-height: 1.6; text-transform: uppercase;">
                                <strong style="font-weight: 700;">Email:</strong> {{ $email }}
                            </td>
                        </tr>
                    @endif

                    @if ($phone)
                        <tr>
                            <td style="padding: 6px 0; color: #000000; font-family: 'Inter', Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 300; line-height: 1.6; text-transform: uppercase;">
                                <strong style="font-weight: 700;">Phone:</strong> {{ $phone }}
                            </td>
                        </tr>
                    @endif

                    @if ($instagram)
                        <tr>
                            <td style="padding: 6px 0; color: #000000; font-family: 'Inter', Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 300; line-height: 1.6; text-transform: uppercase;">
                                <strong style="font-weight: 700;">Instagram:</strong> {{ $instagram }}
                            </td>
                        </tr>
                    @endif

                    @if ($message)
                        <tr>
                            <td style="padding: 18px 0 6px 0; color: #000000; font-family: 'Inter', Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 300; line-height: 1.6; text-transform: none;">
                                <strong style="font-weight: 700; text-transform: uppercase;">Message:</strong><br>
                                {{ $message }}
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
@endsection

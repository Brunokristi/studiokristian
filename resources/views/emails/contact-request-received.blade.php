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
                    {{ $locale === 'sk' ? 'Ďakujeme' : 'Thank you' }}
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
                    {{ $locale === 'sk' ? 'Ahoj '.$name.',' : 'Hi '.$name.',' }}
                </p>


                <p
                    style="
                        margin: 0 auto;
                        max-width: 460px;
                        color: #000000;
                        font-family: 'Inter', Arial, Helvetica, sans-serif;
                        font-size: 14px;
                        font-weight: 300;
                        line-height: 1.6;
                        text-transform: uppercase;
                    "
                >
                    @if ($locale === 'sk')
                        Vašu požiadavku sme prijali. Čoskoro sa vám ozveme na tento e-mail.
                    @else
                        We received your request. We will get back to you shortly by email.
                    @endif
                </p>
            </td>
        </tr>
    </table>
@endsection

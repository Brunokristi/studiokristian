<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="color-scheme"
        content="light"
    >

    <meta
        name="supported-color-schemes"
        content="light"
    >

    <title>
        {{ $subject ?? config('app.name') }}
    </title>

    <!--[if !mso]><!-->
    <style type="text/css">
        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 300;
            src: url('{{ rtrim(config('app.mail_asset_url'), '/') }}/fonts/Inter/static/Inter_18pt-Light.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 700;
            src: url('{{ rtrim(config('app.mail_asset_url'), '/') }}/fonts/Inter/static/Inter_18pt-Bold.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Space Mono';
            font-style: normal;
            font-weight: 400;
            src: url('{{ rtrim(config('app.mail_asset_url'), '/') }}/fonts/Space_Mono/SpaceMono-Regular.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Space Mono';
            font-style: normal;
            font-weight: 700;
            src: url('{{ rtrim(config('app.mail_asset_url'), '/') }}/fonts/Space_Mono/SpaceMono-Bold.ttf') format('truetype');
        }
    </style>
    <!--<![endif]-->

    <!--[if mso]>
    <style type="text/css">
        body, table, td, p, a { font-family: Arial, Helvetica, sans-serif !important; }
        h1, h2, h3 { font-family: 'Courier New', Courier, monospace !important; }
    </style>
    <![endif]-->
</head>

<body
    style="
        margin: 0;
        padding: 0;
        background: #ffffff;
        color: #000000;
        font-family: 'Inter', Arial, Helvetica, sans-serif;
    "
>
    <!-- Preview text -->
    <div
        style="
            display: none;
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            color: transparent;
        "
    >
        {{ $preview ?? $subject ?? '' }}
    </div>


    <table
        role="presentation"
        width="100%"
        cellspacing="0"
        cellpadding="0"
        border="0"
        style="
            width: 100%;
            margin: 0;
            padding: 0;
            border-collapse: collapse;
            background: #ffffff;
        "
    >
        <!-- HEADER -->
        <tr>
            <td
                align="center"
                style="
                    padding: 0;
                    background: #ffffff;
                "
            >
                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="
                        width: 100%;
                        max-width: 1440px;
                        border-collapse: collapse;
                    "
                >
                    <tr>
                        <td
                            style="
                                padding: 18px 20px;
                            "
                        >
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
                                    <!-- Studio -->
                                    <td
                                        width="33.333%"
                                        valign="middle"
                                        align="left"
                                        style="
                                            font-family: 'Space Mono', Courier, monospace;
                                            font-size: 13px;
                                            font-weight: 700;
                                            line-height: 1;
                                            letter-spacing: 0;
                                            color: #133EB4;
                                        "
                                    >
                                        studio
                                    </td>


                                    <!-- Logo mark -->
                                    <td
                                        width="33.333%"
                                        valign="middle"
                                        align="center"
                                    >
                                        <img
                                            src="{{ $message->embed(public_path('assets/logo.png')) }}"
                                            alt="Studio Kristian"
                                            width="51"
                                            style="
                                                display: inline-block;
                                                width: 51px;
                                                max-width: 51px;
                                                height: auto;
                                                border: 0;
                                                outline: none;
                                                text-decoration: none;
                                            "
                                        >
                                    </td>


                                    <!-- Kristian -->
                                    <td
                                        width="33.333%"
                                        valign="middle"
                                        align="right"
                                        style="
                                            font-family: 'Space Mono', 'Courier New', Courier, monospace;
                                            font-size: 13px;
                                            font-weight: 700;
                                            line-height: 1;
                                            letter-spacing: 0;
                                            color: #000000;
                                        "
                                    >
                                        kristian
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>


        <!-- BODY -->
        <tr>
            <td
                align="center"
                style="
                    padding: 0;
                    background: #ffffff;
                "
            >
                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="
                        width: 100%;
                        max-width: 760px;
                        border-collapse: collapse;
                    "
                >
                    <tr>
                        <td
                            style="
                                padding:
                                    88px
                                    24px
                                    110px
                                    24px;
                                color: #000000;
                                font-family: 'Inter', Helvetica, sans-serif;
                                font-size: 14px;
                                font-weight: 300;
                                line-height: 1.65;
                            "
                        >
                            @yield('content')
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <!-- FOOTER -->
        <tr>
            <td
                align="center"
                style="
                    padding: 0;
                    background: #133EB4;
                "
            >
                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="
                        width: 100%;
                        max-width: 1440px;
                        border-collapse: collapse;
                    "
                >
                    <tr>
                        <td
                            style="
                                padding:
                                    28px
                                    20px
                                    24px
                                    20px;
                            "
                        >
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
                                    <!-- Copyright -->
                                    <td
                                        valign="middle"
                                        align="left"
                                        style="
                                            color: #ffffff;
                                            font-family: 'Inter', Arial, Helvetica, sans-serif;
                                            font-size: 10px;
                                            font-weight: 300;
                                            line-height: 1.5;
                                            text-transform: uppercase;
                                        "
                                    >
                                        © {{ now()->year }} STUDIO KRISTIAN.
                                        VŠETKY PRÁVA VYHRADENÉ.
                                    </td>

                                    <!-- Website -->
                                    <td
                                        valign="middle"
                                        align="right"
                                    >
                                        <a
                                            href="{{ config('app.url') }}"
                                            style="
                                                color: #ffffff;
                                                font-family: 'Inter', Arial, Helvetica, sans-serif;
                                                font-size: 10px;
                                                font-weight: 300;
                                                line-height: 1.5;
                                                text-decoration: underline;
                                                text-transform: uppercase;
                                            "
                                        >
                                            STUDIOKRISTIAN.COM
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
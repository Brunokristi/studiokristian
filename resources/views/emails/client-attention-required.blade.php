@extends('emails.layout')

@section('content')
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center">
                <h1 style="margin: 0 0 24px 0; color: #000000; font-family: 'Space Mono', 'Courier New', Courier, monospace; font-size: 30px; font-weight: 700; line-height: 1.2; letter-spacing: 0; text-transform: uppercase;">
                    Your attention is required
                </h1>

                <p style="margin: 0 0 10px 0; color: #000000; font-family: 'Inter', Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 300; line-height: 1.6; text-transform: uppercase;">
                    Hello {{ $recipientName }},
                </p>

                <p style="margin: 0 auto 30px auto; max-width: 460px; color: #000000; font-family: 'Inter', Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 300; line-height: 1.6; text-transform: uppercase;">
                    There are items waiting for you in the client portal.
                </p>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; max-width: 460px; margin: 0 auto 36px auto; border-collapse: collapse; border-top: 1px solid #133EB4;">
                    @if ($invoiceCount > 0)
                        <tr>
                            <td style="padding: 16px 0; border-bottom: 1px solid #133EB4; color: #000000; font-family: 'Inter', Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 300; line-height: 1.6; text-transform: uppercase;">
                                {{ $invoiceCount === 1 ? '1 invoice awaiting payment' : $invoiceCount.' invoices awaiting payment' }}
                            </td>
                        </tr>
                    @endif

                    @if ($signatureCount > 0)
                        <tr>
                            <td style="padding: 16px 0; border-bottom: 1px solid #133EB4; color: #000000; font-family: 'Inter', Arial, Helvetica, sans-serif; font-size: 14px; font-weight: 300; line-height: 1.6; text-transform: uppercase;">
                                {{ $signatureCount === 1 ? '1 document awaiting your signature' : $signatureCount.' documents awaiting your signature' }}
                            </td>
                        </tr>
                    @endif
                </table>

                <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin: 0 auto; border-collapse: collapse;">
                    <tr>
                        <td align="center" style="border: 1px solid #000000;">
                            <a href="{{ $actionUrl }}" style="display: inline-block; padding: 13px 22px; color: #000000; font-family: 'Space Mono', 'Courier New', Courier, monospace; font-size: 12px; font-weight: 700; line-height: 1.4; letter-spacing: 0; text-decoration: none; text-transform: uppercase;">
                                Open Client Portal
                            </a>
                        </td>
                    </tr>
                </table>

                <p style="margin: 42px auto 0 auto; max-width: 420px; color: #777777; font-family: 'Inter', Arial, Helvetica, sans-serif; font-size: 11px; font-weight: 300; line-height: 1.6; text-transform: uppercase;">
                    Invoices and documents are available securely in the portal. No files are attached to this email.
                </p>
            </td>
        </tr>
    </table>
@endsection
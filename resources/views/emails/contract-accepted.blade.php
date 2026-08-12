@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 24px; font-family: 'Space Mono', 'Courier New', Courier, monospace; font-size: 30px; line-height: 1.15;">Dokument bol prijatý</h1>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin: 0 0 28px; border-top: 1px solid #d8d6cf;">
        @foreach ($details as $label => $value)
            <tr>
                <td style="width: 36%; padding: 10px 8px 10px 0; border-bottom: 1px solid #d8d6cf; color: #66645e; font-size: 13px;">{{ $label }}</td>
                <td style="padding: 10px 0 10px 8px; border-bottom: 1px solid #d8d6cf; font-size: 13px; font-weight: 700;">{{ $value }}</td>
            </tr>
        @endforeach
    </table>
    <p style="margin: 0;">
        <a href="{{ $actionUrl }}" style="display: inline-block; padding: 14px 20px; background: #101010; color: #ffffff; font-family: 'Space Mono', 'Courier New', Courier, monospace; font-size: 14px; font-weight: 700; text-decoration: none;">Otvoriť prijatý dokument &rarr;</a>
    </p>
@endsection
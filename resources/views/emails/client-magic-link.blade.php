@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 24px; font-family: 'Space Mono', 'Courier New', Courier, monospace; font-size: 30px; line-height: 1.15;">Prihlásenie do Client Portal</h1>
    <p style="margin: 0 0 16px;">Dobrý deň, {{ $recipientName }},</p>
    <p style="margin: 0 0 28px;">Tento jednorazový odkaz vás bezpečne prihlási do Client Portal. Platí 10 minút.</p>
    <p style="margin: 0 0 28px;">
        <a href="{{ $actionUrl }}" style="display: inline-block; padding: 14px 20px; background: #101010; color: #ffffff; font-family: 'Space Mono', 'Courier New', Courier, monospace; font-size: 14px; font-weight: 700; text-decoration: none;">Otvoriť Client Portal &rarr;</a>
    </p>
    <p style="margin: 0; color: #66645e; font-size: 14px;">Ak ste o prihlásenie nežiadali, tento email môžete ignorovať.</p>
@endsection
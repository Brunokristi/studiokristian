@extends('emails.layout')


@section('content')
    <h1 style="margin: 0 0 24px; font-family: 'Space Mono', 'Courier New', Courier, monospace; font-size: 30px; line-height: 1.15;">Ticket assigned to you</h1>

    <p style="margin: 0 0 16px;"><strong>{{ $projectName }}</strong> has a ticket waiting for your attention.</p>
    <p style="margin: 0 0 8px; font-family: 'Space Mono', 'Courier New', Courier, monospace; font-weight: 700;">{{ $ticketTitle }}</p>
    <p style="margin: 0 0 28px; color: #66645e;">{{ $ticketDescription }}</p>

    <p style="margin: 0;">
        <a href="{{ $actionUrl }}" style="display: inline-block; padding: 14px 20px; background: #101010; color: #ffffff; font-family: 'Space Mono', 'Courier New', Courier, monospace; font-size: 14px; font-weight: 700; text-decoration: none;">Open ticket board &rarr;</a>
    </p>
@endsection

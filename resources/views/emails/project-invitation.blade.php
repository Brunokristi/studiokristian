@extends('emails.layout')

@section('content')
    <h1 style="margin: 0 0 24px; font-family: 'Space Mono', 'Courier New', Courier, monospace; font-size: 30px; line-height: 1.15;">Project invitation</h1>
    <p style="margin: 0 0 16px;">Hello,</p>
    <p style="margin: 0 0 28px;">You have been invited to collaborate on <strong>{{ $projectName }}</strong>.</p>
    <p style="margin: 0;">
        <a href="{{ $actionUrl }}" style="display: inline-block; padding: 14px 20px; background: #101010; color: #ffffff; font-family: 'Space Mono', 'Courier New', Courier, monospace; font-size: 14px; font-weight: 700; text-decoration: none;">Open project &rarr;</a>
    </p>
@endsection
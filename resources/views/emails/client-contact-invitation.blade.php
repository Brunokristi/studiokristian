@extends('emails.layout')


@section('content')
    <h1>
        You’re invited
    </h1>

    <p>
        Hello{{ $recipientName ? ' '.$recipientName : '' }},
    </p>

    <p>
        You have been invited to access the client portal for
        <strong>{{ $companyName }}</strong>.
    </p>

    <p>
        <a href="{{ $actionUrl }}">
            Open client portal
        </a>
    </p>

    <p>
        If you did not expect this invitation, you can safely ignore this email.
    </p>
@endsection
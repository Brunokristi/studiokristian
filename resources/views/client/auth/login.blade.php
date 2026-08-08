@extends('layouts.client', ['title' => 'Prihlásenie'])

@section('content')
<section style="max-width: 520px; margin: 6vh auto 0;">
    <p class="portal-muted" style="font-size: 12px; text-transform: uppercase;">Bezpečný prístup</p>
    <h1 style="font-size: clamp(32px, 7vw, 56px); line-height: 1; margin: 14px 0 18px; letter-spacing: 0;">Váš projekt,<br>na jednom mieste.</h1>
    <p class="portal-muted" style="font-family: sans-serif; line-height: 1.55; margin-bottom: 32px;">Zadajte pracovný email. Pošleme vám jednorazový prihlasovací odkaz platný 10 minút.</p>

    @if (session('status'))
        <div class="portal-panel" role="status" style="padding: 16px; margin-bottom: 20px; border-color: #718315;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="portal-panel" role="alert" style="padding: 16px; margin-bottom: 20px; border-color: #9f2d20;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('client.login.send') }}">
        @csrf
        <label class="portal-label" for="email">Email</label>
        <input class="portal-input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        <button class="portal-button" style="width: 100%; margin-top: 12px; background: var(--portal-accent); color: var(--portal-ink);" type="submit">Poslať prihlasovací odkaz <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
    </form>
</section>
@endsection
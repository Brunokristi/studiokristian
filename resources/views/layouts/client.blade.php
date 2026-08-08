<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Client Portal' }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root { --portal-ink: #101010; --portal-paper: #f4f3ef; --portal-line: #d8d6cf; --portal-accent: #d9ff43; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--portal-paper); color: var(--portal-ink); }
        .portal-shell { min-height: 100vh; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .portal-topbar { height: 64px; border-bottom: 1px solid var(--portal-line); display: flex; align-items: center; justify-content: space-between; padding: 0 20px; }
        .portal-brand { color: inherit; text-decoration: none; font-size: 13px; font-weight: 700; text-transform: uppercase; }
        .portal-main { width: min(1120px, 100%); margin: 0 auto; padding: 40px 20px 80px; }
        .portal-button { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; gap: 8px; border: 1px solid var(--portal-ink); border-radius: 4px; padding: 0 18px; background: var(--portal-ink); color: white; font: inherit; font-size: 13px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .portal-button--quiet { background: transparent; color: var(--portal-ink); }
        .portal-input { width: 100%; min-height: 48px; border: 1px solid var(--portal-line); border-radius: 4px; background: white; padding: 0 14px; font: inherit; font-size: 16px; }
        .portal-label { display: block; margin-bottom: 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .portal-panel { border: 1px solid var(--portal-line); border-radius: 6px; background: white; }
        .portal-muted { color: #66645e; }
        @media (min-width: 768px) { .portal-topbar { padding: 0 40px; } .portal-main { padding-top: 64px; } }
    </style>
</head>
<body>
<div class="portal-shell">
    <header class="portal-topbar">
        <a class="portal-brand" href="{{ auth('client')->check() ? route('client.dashboard') : route('client.login') }}">Studio Kristian / Client Portal</a>
        @auth('client')
            <form method="POST" action="{{ route('client.logout') }}">@csrf<button class="portal-button portal-button--quiet" type="submit">Odhlásiť</button></form>
        @endauth
    </header>
    <main class="portal-main">@yield('content')</main>
</div>
</body>
</html>
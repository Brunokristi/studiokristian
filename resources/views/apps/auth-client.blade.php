<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Client Login · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div
        id="client-login"
        data-status="{{ session('status') }}"
        data-error="{{ $errors->first() }}"
        data-email="{{ old('email') }}"
    ></div>
</body>
</html>

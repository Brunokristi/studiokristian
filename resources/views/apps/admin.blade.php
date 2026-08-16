<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Client Portal Admin · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light text-dark antialiased">
    <div id="client-portal-admin"></div>
    <script id="client-portal-admin-user" type="application/json">{!! json_encode([
        'id' => auth()->id(),
        'is_admin' => (bool) (auth()->user()?->is_admin),
        'role' => method_exists(auth()->user(), 'portalRole') ? auth()->user()?->portalRole() : null,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</body>
</html>

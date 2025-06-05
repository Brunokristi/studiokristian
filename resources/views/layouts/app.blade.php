<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="bg-white text-black font-mono">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Studio Kristian')</title>

    <!-- styles and scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- google fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&family=Tai+Heritage+Pro:wght@400;700&display=swap" rel="stylesheet">

    <!-- bootstrap icons -->

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

</head>
<body class="font-sans text-base bg-bg">
    <x-navbar />

    <main>
        @include('sections.hero')
    </main>
</body>
</html>

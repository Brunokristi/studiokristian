<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>studio kristian | Branding, Web & App Development, AI Integration</title>
    <meta name="description" content="studio kristian, founded by Bruno Kristián, helps businesses with branding, custom website and web application development, AI integration, digital infrastructure and technology consulting.">
    <meta name="robots" content="index, follow">
    <meta name="author" content="Bruno Kristián">
    <link rel="canonical" href="{{ url()->current() }}">

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/assets/logo.png">
    <meta name="theme-color" content="#000000">

    <meta property="og:site_name" content="studio kristian">
    <meta property="og:type" content="website">
    <meta property="og:title" content="studio kristian | Branding, Web & App Development, AI Integration">
    <meta property="og:description" content="studio kristian, founded by Bruno Kristián, helps businesses with branding, custom website and web application development, AI integration, digital infrastructure and technology consulting.">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('assets/logo.png') }}">
    <meta property="og:locale" content="{{ app()->getLocale() }}">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="studio kristian | Branding, Web & App Development, AI Integration">
    <meta name="twitter:description" content="studio kristian, founded by Bruno Kristián, helps businesses with branding, custom website and web application development, AI integration, digital infrastructure and technology consulting.">
    <meta name="twitter:image" content="{{ asset('assets/logo.png') }}">

    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@type' => 'ProfessionalService',
            'name' => 'studio kristian',
            'legalName' => 'kstdio, s.r.o.',
            'url' => config('app.url'),
            'logo' => asset('assets/logo.png'),
            'image' => asset('assets/logo.png'),
            'email' => 'hello@studiokristian.com',
            'telephone' => '+421911454678',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Bratislava',
                'addressCountry' => 'SK',
            ],
            'areaServed' => ['Slovakia', 'Remote'],
            'founder' => [
                '@type' => 'Person',
                'name' => 'Bruno Kristián',
                'jobTitle' => 'Founder',
            ],
            'sameAs' => [
                'https://www.instagram.com/studiokristian/',
                'https://www.facebook.com/profile.php?id=61574392799883',
            ],
            'hasOfferCatalog' => [
                '@type' => 'OfferCatalog',
                'name' => 'Services',
                'itemListElement' => [
                    [
                        '@type' => 'Offer',
                        'itemOffered' => [
                            '@type' => 'Service',
                            'name' => 'Branding & Marketing',
                            'description' => 'Branding, brand identity, visual identity, logo design, brand strategy, marketing strategy, social media and graphic design.',
                        ],
                    ],
                    [
                        '@type' => 'Offer',
                        'itemOffered' => [
                            '@type' => 'Service',
                            'name' => 'Website Development (Webové stránky na mieru)',
                            'description' => 'Custom website development, tvorba webových stránok, web na mieru, firemná a moderná webstránka.',
                        ],
                    ],
                    [
                        '@type' => 'Offer',
                        'itemOffered' => [
                            '@type' => 'Service',
                            'name' => 'Web Application Development (Webové aplikácie na mieru)',
                            'description' => 'Webová aplikácia a softvér na mieru, podnikový a interný systém, klientsky portál, rezervačný systém.',
                        ],
                    ],
                    [
                        '@type' => 'Offer',
                        'itemOffered' => [
                            '@type' => 'Service',
                            'name' => 'AI Integration & Development (AI integrácia a vývoj)',
                            'description' => 'AI pre firmy, AI riešenia a automatizácia, AI chatbot a asistent, integrácia AI, LLM, OCR a AI spracovanie dokumentov.',
                        ],
                    ],
                    [
                        '@type' => 'Offer',
                        'itemOffered' => [
                            '@type' => 'Service',
                            'name' => 'Digital Infrastructure & Services (Digitálna infraštruktúra a služby)',
                            'description' => 'Hosting, webhosting, správa servera, VPS, doména, firemný email, Google Workspace, Cloudflare, DNS, SSL, správa IT infraštruktúry.',
                        ],
                    ],
                    [
                        '@type' => 'Offer',
                        'itemOffered' => [
                            '@type' => 'Service',
                            'name' => 'Consulting (Konzultácie)',
                            'description' => 'Digitálna a technologická konzultácia, digitálna stratégia, IT konzultácie, AI konzultácie, digitálny a technologický audit, automatizácia procesov.',
                        ],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <script>
        window.__GA_MEASUREMENT_ID = 'G-9JDEV1MKE2';
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('consent', 'default', {
            ad_storage: 'denied',
            analytics_storage: 'denied',
            functionality_storage: 'granted',
            personalization_storage: 'denied',
            security_storage: 'granted',
        });
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-9JDEV1MKE2"></script>
    <script>
        gtag('js', new Date());
        gtag('config', 'G-9JDEV1MKE2', {
            anonymize_ip: true,
            send_page_view: false,
        });
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>

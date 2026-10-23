<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur']) ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inertia signs its own requests from the XSRF cookie, and so does the
             builder's image upload, which posts with fetch() — see
             resources/js/lib/csrf.ts. This tag is only its fallback: it is
             written once per page load and goes stale after an Inertia login. --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'متجر برو') }}</title>

        {{-- Paint the canvas before first byte of CSS so there is no white flash
             on the ivory background, and no white flash in dark mode. --}}
        <script>
            (function () {
                var stored = localStorage.getItem('appearance');
                var dark = stored === 'dark' || (!stored && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (dark) document.documentElement.classList.add('dark');
                document.documentElement.style.backgroundColor = dark ? '#0A100E' : '#FAF9F4';
            })();
        </script>

        <link rel="icon" href="/logo.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/logo.svg">

        <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:300,400,500,600,700" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>

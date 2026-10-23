<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur']) ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        {{-- Saved to the home screen, the dashboard opens full-screen like an app
             and the status bar takes the colour of the bar under it. --}}
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="متجر برو">
        <meta name="theme-color" content="#F3F2EF" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0A100E" media="(prefers-color-scheme: dark)">

        {{-- Inertia signs its own requests from the XSRF cookie. This is for the
             one place that does not go through Inertia: the builder's image
             upload, which posts a FormData straight to the server so it can
             show progress and get the stored path back. --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'متجر برو') }}</title>

        {{-- Paint the canvas before first byte of CSS so there is no white flash
             on the grouped canvas, and no white flash in dark mode. --}}
        <script>
            (function () {
                var stored = localStorage.getItem('appearance');
                var dark = stored === 'dark' || (!stored && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (dark) document.documentElement.classList.add('dark');
                document.documentElement.style.backgroundColor = dark ? '#070A09' : '#F4F3F0';
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

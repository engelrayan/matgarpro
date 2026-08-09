<!DOCTYPE html>
<html lang="ar" dir="rtl" class="scroll-smooth no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'متجر برو')</title>
    <meta name="description" content="@yield('description')">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="ar_EG">
    <meta property="og:title" content="@yield('title', 'متجر برو')">
    <meta property="og:description" content="@yield('description')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" href="/logo.svg" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:400,500,600,700" rel="stylesheet">

    @vite('resources/css/app.css')

    {{-- Drops the no-js guard the instant scripting is confirmed, so the
         reveal animations can take over from the always-visible fallback. --}}
    <script>document.documentElement.classList.remove('no-js');</script>
</head>
<body class="bg-background font-sans text-foreground antialiased">

{{-- Static, not sticky. A floating bar covers section headings on scroll —
     visible in every competitor screenshot we looked at — and on a page this
     short it buys nothing. --}}
<header class="border-b border-border">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5">
            @include('partials.logo', ['class' => 'size-9 text-primary'])
            <span class="text-lg font-bold tracking-tight">متجر برو</span>
        </a>

        <nav class="flex items-center gap-2">
            @auth
                <a href="{{ route('dashboard') }}" class="btn-primary">لوحة التحكم</a>
            @else
                <a href="{{ route('login') }}" class="btn-ghost">دخول</a>
                <a href="{{ route('register') }}" class="btn-primary">ابدأ مجانًا</a>
            @endauth
        </nav>
    </div>
</header>

<main>
    @yield('content')
</main>

<footer class="border-t border-border bg-card">
    <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 py-8 text-sm text-muted-foreground sm:flex-row">
        <p>© {{ date('Y') }} متجر برو</p>
        <p>صُنع في مصر</p>
    </div>
</footer>

@stack('scripts')
</body>
</html>

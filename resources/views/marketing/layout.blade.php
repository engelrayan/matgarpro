<!DOCTYPE html>
<html lang="ar" dir="rtl" class="scroll-smooth no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#FAF9F4">

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
<body class="bg-background font-sans text-foreground antialiased [&_section[id]]:scroll-mt-16">

{{-- A translucent bar, as on apple.com: thin, and the page visibly scrolls
     underneath it. Sticky now that the page is long enough to need the sign-up
     button within reach; anchored sections set `scroll-margin-top` so a jump
     never lands a heading under it. --}}
<header class="material hairline-b sticky top-0 z-50">
    <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4 md:px-6">
        <a href="{{ route('home') }}" class="flex items-center gap-2">
            @include('partials.logo', ['class' => 'size-8 text-primary'])
            <span class="text-[17px] font-bold">متجر برو</span>
        </a>

        <nav class="hidden items-center gap-1 text-[14px] text-muted-foreground md:flex">
            <a href="#ai" class="rounded-lg px-3 py-1.5 transition-colors hover:text-foreground">الذكاء الاصطناعي</a>
            <a href="#shipping" class="rounded-lg px-3 py-1.5 transition-colors hover:text-foreground">الشحن والتحصيل</a>
            <a href="#pricing" class="rounded-lg px-3 py-1.5 transition-colors hover:text-foreground">الأسعار</a>
        </nav>

        <nav class="flex items-center gap-1.5">
            @auth
                <a href="{{ route('dashboard') }}" class="btn-primary !min-h-[34px] !rounded-full !px-4 !text-[14px]">لوحة التحكم</a>
            @else
                <a href="{{ route('login') }}" class="btn-ghost !min-h-[34px] !px-3 !text-[14px]">دخول</a>
                <a href="{{ route('register') }}" class="btn-primary !min-h-[34px] !rounded-full !px-4 !text-[14px]">ابدأ مجانًا</a>
            @endauth
        </nav>
    </div>
</header>

<main>
    @yield('content')
</main>

<footer class="hairline-t bg-card pb-safe">
    <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 py-8 text-sm text-muted-foreground sm:flex-row">
        <p>© {{ date('Y') }} متجر برو</p>
        <p>صُنع في مصر</p>
    </div>
</footer>

@stack('scripts')
</body>
</html>

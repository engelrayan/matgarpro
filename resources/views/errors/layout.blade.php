<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title') — متجر برو</title>
    {{-- An error page has nothing worth indexing and its URL is usually a
         mistake; letting it into search results is how a typo becomes a
         permanent listing. --}}
    <meta name="robots" content="noindex,nofollow">

    <link rel="icon" href="/logo.svg" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:400,500,600,700" rel="stylesheet">

    @vite('resources/css/app.css')
</head>
<body class="mesh flex min-h-dvh flex-col bg-background font-sans text-foreground antialiased">

<div class="grid-lines pointer-events-none fixed inset-0"></div>

<main class="relative flex flex-1 items-center justify-center px-6 py-16">
    <div class="w-full max-w-md text-center">
        <a href="{{ url('/') }}" class="mx-auto mb-10 flex w-fit items-center gap-2.5">
            @include('partials.logo', ['class' => 'size-10 text-primary'])
            <span class="text-lg font-bold tracking-tight">متجر برو</span>
        </a>

        <div class="surface-lux halo relative p-9">
            <p class="text-foil tabular text-6xl font-bold tracking-tight md:text-7xl">
                @yield('code')
            </p>

            <h1 class="mt-5 text-xl font-bold tracking-tight">@yield('title')</h1>

            <p class="mx-auto mt-3 max-w-xs text-balance text-sm leading-relaxed text-muted-foreground">
                @yield('body')
            </p>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-2">
                @yield('actions')
                <a href="{{ url('/') }}" class="btn-outline">الصفحة الرئيسية</a>
            </div>
        </div>
    </div>
</main>

</body>
</html>

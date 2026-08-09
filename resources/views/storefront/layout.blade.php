<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', $store->name)</title>
    <meta name="description" content="@yield('description')">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="ar_EG">
    <meta property="og:site_name" content="{{ $store->name }}">
    <meta property="og:title" content="@yield('title', $store->name)">
    <meta property="og:description" content="@yield('description')">
    @hasSection('image')
        <meta property="og:image" content="@yield('image')">
        <meta name="twitter:card" content="summary_large_image">
    @endif

    <link rel="canonical" href="{{ $store->canonicalUrl() . request()->getRequestUri() }}">

    <link rel="icon" href="/logo.svg" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    {{-- The theme's own typeface, not the platform's. --}}
    <link href="{{ app(\App\Services\Storefront\ThemeResolver::class)->fontUrl($theme) }}" rel="stylesheet">

    @vite('resources/css/app.css')

    {{-- The merchant's theme, inline and after the stylesheet.

         Inline because it is a few hundred bytes and a separate request would
         show the platform's colours for the first paint of every page — the
         customer would watch the store change colour as it loads. --}}
    <style>{!! app(\App\Services\Storefront\ThemeResolver::class)->cssVariables($theme) !!}</style>

    @stack('head')
</head>
<body class="bg-background font-sans text-foreground antialiased" data-layout="{{ $theme['layout'] }}">

{{-- Showroom notice. A demo that looks like a real shop has to say so, or
     someone eventually tries to buy from it.

     One quiet line, not a scrolling marquee: the ribbon is a disclaimer, and
     an animated black band shouting across every page is the loudest thing on
     a screen whose whole job is to show off the theme underneath it. --}}
@if ($store->is_demo)
    <div class="border-b border-border bg-muted/60 px-5 py-2 text-center text-xs text-muted-foreground">
        معرض للثيم — المنتجات والأسعار للعرض بس.
        <a href="{{ config('app.url') }}/register" class="font-medium text-foreground underline underline-offset-2">
            اعمل متجرك
        </a>
    </div>
@endif


{{-- Header, as the merchant arranged it in the builder.

     A product page may still drop it entirely: for a page whose only job is to
     convert ad traffic, every other link is somewhere else to go. That switch
     stays on the product, not in the builder — it is a per-product decision. --}}
@unless ($hideHeader ?? false)
    @include('storefront.partials.sections', [
        'sections' => $headerSections ?? [],
        'sectionData' => $chromeData,
    ])
@endunless

<main>
    @yield('content')
</main>

<footer class="mt-16 border-t border-border bg-muted/30">
    @include('storefront.partials.sections', [
        'sections' => $footerSections ?? [],
        'sectionData' => $chromeData,
    ])
</footer>

{{-- Builder preview only. Never rendered for a customer. --}}
@if (app(\App\Services\Builder\PageRenderer::class)->isPreview(request()))
    @include('storefront.partials.builder-bridge', [
        'builderOrigin' => rtrim(config('app.url'), '/'),
    ])
@else
    {{-- Last in the body: tracking never delays the page a merchant paid to
         show. Skipped in preview: a merchant laying out their shop should not
         be firing ViewContent events at their own pixel. --}}
    @include('storefront.partials.pixels')
@endif

@stack('scripts')
</body>
</html>

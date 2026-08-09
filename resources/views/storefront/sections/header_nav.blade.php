{{--
    The storefront header, in three shapes chosen by the theme.

      classic   logo on the reading edge, links beside it, search on the far
                side. What most shops want.
      centered  logo alone on its own row with the links centred underneath.
                Costs vertical space and buys the look every jewellery and
                perfume site on earth has — because it works for them.
      minimal   logo and nothing else. For a shop with four products, a search
                box searches nothing and a category strip is noise the visitor
                reads past on the way to the buy button.

    The merchant's own switches (sticky, search, which links) still apply on
    top — the theme decides the shape, the merchant decides the contents.
--}}
@php
    $shape = $theme['header'] ?? 'classic';

    $navLinks = match ($settings['links_source']) {
        'manual' => collect($settings['links'])->filter(fn ($l) => filled($l['label']) && filled($l['url']))
            ->map(fn ($l) => ['label' => $l['label'], 'url' => $l['url']]),
        'categories' => $navCategories->take(5)
            ->map(fn ($c) => ['label' => $c->name, 'url' => route('storefront.category', $c->slug)]),
        default => collect(),
    };

    // `minimal` drops both regardless of what the merchant ticked: the theme
    // exists precisely to remove them, and honouring the toggle here would
    // make the theme look broken rather than deliberate.
    $showSearch = $settings['show_search'] && $shape !== 'minimal';
    $showLinks = $navLinks->isNotEmpty() && $shape !== 'minimal';
@endphp

<header @class([
    'z-40 border-b border-border bg-card/95 backdrop-blur',
    'sticky top-0' => $settings['sticky'],
])>
    @if ($shape === 'centered')
        <div class="mx-auto max-w-5xl px-5 py-4 text-center">
            <a href="{{ route('storefront.home') }}" class="inline-flex flex-col items-center gap-2">
                @if ($logo = $store->logoUrl())
                    <img src="{{ $logo }}" alt="{{ $store->name }}" class="size-12 rounded-[--radius] object-cover">
                @endif
                <span class="text-lg font-bold tracking-tight">{{ $store->name }}</span>
            </a>

            @if ($showLinks)
                <nav class="mt-3 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm">
                    @foreach ($navLinks as $link)
                        <a href="{{ $link['url'] }}" class="text-muted-foreground transition-colors hover:text-foreground">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
            @endif

            @if ($showSearch)
                <form action="{{ route('storefront.search') }}" method="GET" class="relative mx-auto mt-3 w-full max-w-sm">
                    <input type="search" name="q" value="{{ $term ?? '' }}"
                           placeholder="دوّر على منتج…"
                           class="w-full rounded-[--radius] border border-input bg-background py-2 pr-9 pl-3 text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
                    <svg class="absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                    </svg>
                </form>
            @endif
        </div>

    @elseif ($shape === 'minimal')
        <div class="mx-auto flex max-w-5xl items-center justify-center px-5 py-4">
            <a href="{{ route('storefront.home') }}" class="flex items-center gap-2.5">
                @if ($logo = $store->logoUrl())
                    <img src="{{ $logo }}" alt="{{ $store->name }}" class="size-9 rounded-[--radius] object-cover">
                @endif
                <span class="font-bold tracking-tight">{{ $store->name }}</span>
            </a>
        </div>

    @else
        <div class="mx-auto flex max-w-5xl items-center gap-3 px-5 py-3">
            <a href="{{ route('storefront.home') }}" class="flex shrink-0 items-center gap-2.5">
                @if ($logo = $store->logoUrl())
                    <img src="{{ $logo }}" alt="{{ $store->name }}" class="size-9 rounded-[--radius] object-cover">
                @else
                    <span class="flex size-9 items-center justify-center rounded-[--radius] bg-primary text-base font-bold text-primary-foreground">
                        {{ mb_substr($store->name, 0, 1) }}
                    </span>
                @endif
                <span class="font-bold tracking-tight">{{ $store->name }}</span>
            </a>

            {{-- Links inline on desktop; the search box carries the phone,
                 where a customer who knows what they want should not have to
                 scroll a category list to find it. --}}
            @if ($showLinks)
                <nav class="mr-4 hidden items-center gap-4 text-sm md:flex">
                    @foreach ($navLinks->take(4) as $link)
                        <a href="{{ $link['url'] }}" class="text-muted-foreground transition-colors hover:text-foreground">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
            @endif

            @if ($showSearch)
                <form action="{{ route('storefront.search') }}" method="GET" class="relative mr-auto w-full max-w-56">
                    <input type="search" name="q" value="{{ $term ?? '' }}"
                           placeholder="دوّر على منتج…"
                           class="w-full rounded-[--radius] border border-input bg-background py-2 pr-9 pl-3 text-sm focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/25">
                    <svg class="absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                    </svg>
                </form>
            @endif
        </div>
    @endif
</header>

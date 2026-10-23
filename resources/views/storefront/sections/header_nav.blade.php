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

    Every shape is an iOS navigation bar: translucent material with a half-point
    rule under it, so the page scrolls visibly beneath instead of vanishing
    behind a white slab.
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

@php
    /*
     | The iOS search field: a filled capsule with no border, the glass on the
     | reading edge. 16px text, or Safari zooms the page in when it is tapped.
     */
    $searchField = 'h-10 w-full appearance-none rounded-[calc(var(--radius)+2px)] border-0 bg-foreground/[0.06] pe-3 ps-9 text-base text-foreground placeholder:text-muted-foreground/80 focus:bg-foreground/[0.08] focus:outline-none focus:ring-2 focus:ring-primary/40 md:text-[15px]';
@endphp

<header @class([
    'material hairline-b z-40',
    'sticky top-0' => $settings['sticky'],
])>
    @if ($shape === 'centered')
        <div class="mx-auto max-w-5xl px-4 pb-3 pt-4 text-center md:px-5">
            <a href="{{ route('storefront.home') }}" class="pressable inline-flex flex-col items-center gap-2">
                @if ($logo = $store->logoUrl())
                    <img src="{{ $logo }}" alt="{{ $store->name }}" class="size-12 rounded-[--radius] object-cover">
                @endif
                <span class="text-headline">{{ $store->name }}</span>
            </a>

            @if ($showLinks)
                {{-- One scrollable line on a phone rather than wrapping into a
                     ragged two-row block under the logo. --}}
                <nav class="scrollbar-none -mx-4 mt-2 flex items-center gap-1 overflow-x-auto px-4 text-[15px] md:justify-center">
                    @foreach ($navLinks as $link)
                        <a href="{{ $link['url'] }}" class="flex min-h-[44px] shrink-0 items-center px-3 text-muted-foreground transition-colors hover:text-foreground">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
            @endif

            @if ($showSearch)
                <form action="{{ route('storefront.search') }}" method="GET" role="search" class="relative mx-auto mt-2 w-full max-w-sm">
                    <input type="search" name="q" value="{{ $term ?? '' }}" placeholder="دوّر على منتج" aria-label="دوّر على منتج" class="{{ $searchField }}">
                    <svg class="pointer-events-none absolute start-3 top-1/2 size-[18px] -translate-y-1/2 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                    </svg>
                </form>
            @endif
        </div>

    @elseif ($shape === 'minimal')
        <div class="mx-auto flex h-14 max-w-5xl items-center justify-center px-4 md:px-5">
            <a href="{{ route('storefront.home') }}" class="pressable flex items-center gap-2.5">
                @if ($logo = $store->logoUrl())
                    <img src="{{ $logo }}" alt="{{ $store->name }}" class="size-8 rounded-[--radius] object-cover">
                @endif
                <span class="text-headline">{{ $store->name }}</span>
            </a>
        </div>

    @else
        <div class="mx-auto flex h-14 max-w-5xl items-center gap-3 px-4 md:h-16 md:px-5">
            <a href="{{ route('storefront.home') }}" class="pressable flex min-w-0 shrink-0 items-center gap-2.5">
                @if ($logo = $store->logoUrl())
                    <img src="{{ $logo }}" alt="{{ $store->name }}" class="size-9 rounded-[--radius] object-cover">
                @else
                    <span class="flex size-9 items-center justify-center rounded-[calc(var(--radius)+2px)] bg-primary text-base font-bold text-primary-foreground">
                        {{ mb_substr($store->name, 0, 1) }}
                    </span>
                @endif
                <span class="max-w-[9rem] truncate text-headline sm:max-w-none">{{ $store->name }}</span>
            </a>

            {{-- Links inline on desktop; the search box carries the phone,
                 where a customer who knows what they want should not have to
                 scroll a category list to find it. --}}
            @if ($showLinks)
                <nav class="ms-4 hidden items-center gap-1 text-[15px] md:flex">
                    @foreach ($navLinks->take(4) as $link)
                        <a href="{{ $link['url'] }}" class="rounded-lg px-3 py-2 text-muted-foreground transition-colors hover:bg-foreground/[0.05] hover:text-foreground">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
            @endif

            @if ($showSearch)
                <form action="{{ route('storefront.search') }}" method="GET" role="search" class="relative ms-auto w-full max-w-60">
                    <input type="search" name="q" value="{{ $term ?? '' }}" placeholder="دوّر على منتج" aria-label="دوّر على منتج" class="{{ $searchField }}">
                    <svg class="pointer-events-none absolute start-3 top-1/2 size-[18px] -translate-y-1/2 text-muted-foreground" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                    </svg>
                </form>
            @endif
        </div>
    @endif
</header>

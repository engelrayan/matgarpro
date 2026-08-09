@php
    $navLinks = match ($settings['links_source']) {
        'manual' => collect($settings['links'])->filter(fn ($l) => filled($l['label']) && filled($l['url']))
            ->map(fn ($l) => ['label' => $l['label'], 'url' => $l['url']]),
        'categories' => $navCategories->take(4)
            ->map(fn ($c) => ['label' => $c->name, 'url' => route('storefront.category', $c->slug)]),
        default => collect(),
    };
@endphp

<header @class([
    'z-40 border-b border-border bg-card/95 backdrop-blur',
    'sticky top-0' => $settings['sticky'],
])>
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

        {{-- Links inline on desktop; the search box carries the phone, where a
             customer who knows what they want should not have to scroll a
             category list to find it. --}}
        @if ($navLinks->isNotEmpty())
            <nav class="mr-4 hidden items-center gap-4 text-sm md:flex">
                @foreach ($navLinks as $link)
                    <a href="{{ $link['url'] }}" class="text-muted-foreground transition-colors hover:text-foreground">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>
        @endif

        @if ($settings['show_search'])
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
</header>

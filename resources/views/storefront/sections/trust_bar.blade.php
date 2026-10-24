{{-- The three objections every first-time cash-on-delivery buyer has, answered
     before they ask.

     A grouped list on a phone — icon tile, title, one line under it — the
     shape of the Settings app, which is the most-read list on an iPhone. On a
     wide screen the same rows sit side by side. --}}
@php
    $paths = [
        'check' => 'M20 6 9 17l-5-5',
        'truck' => 'M3 6h11v10H3zM14 10h4l3 3v3h-7M7 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zM17 19a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z',
        'refresh' => 'M3 12a9 9 0 1 0 9-9M3 12l4-4M3 12l4 4',
        'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z',
        'phone' => 'M4 4h5l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v5a1 1 0 0 1-1 1A17 17 0 0 1 3 5a1 1 0 0 1 1-1z',
        'wallet' => 'M3 7h15a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1V7zM3 7l1-2h12M16 12h2',
    ];
    $items = collect($settings['items'])->filter(fn ($item) => filled($item['title']));
@endphp

@if ($items->isNotEmpty())
<section class="mx-auto max-w-5xl px-4 py-6 md:px-5 md:py-8">
    <div @class([
        'ios-group md:grid',
        'md:grid-cols-2' => $items->count() === 2,
        'md:grid-cols-3' => $items->count() === 3,
        'md:grid-cols-4' => $items->count() >= 4,
    ])>
        @foreach ($items as $item)
            <div class="ios-row min-h-[60px] md:py-4 md:before:hidden">
                <span class="ios-tile">
                    <svg class="size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="{{ $paths[$item['icon']] ?? $paths['check'] }}"/>
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-[15px] font-semibold leading-snug">{{ $item['title'] }}</p>
                    @if ($item['body'])
                        <p class="text-[13px] leading-snug text-muted-foreground">{{ $item['body'] }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

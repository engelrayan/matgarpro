{{-- The three objections every first-time cash-on-delivery buyer has, answered
     before they ask. --}}
@php
    $paths = [
        'check' => 'M20 6 9 17l-5-5',
        'truck' => 'M5 12h14M12 5l7 7-7 7',
        'refresh' => 'M3 12a9 9 0 1 0 9-9M3 12l4-4M3 12l4 4',
        'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z',
        'phone' => 'M4 4h5l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v5a1 1 0 0 1-1 1A17 17 0 0 1 3 5a1 1 0 0 1 1-1z',
        'wallet' => 'M3 7h15a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1V7zM3 7l1-2h12M16 12h2',
    ];
    $items = collect($settings['items'])->filter(fn ($item) => filled($item['title']));
@endphp

@if ($items->isNotEmpty())
<section class="border-y border-border">
    <div class="mx-auto grid max-w-5xl gap-4 px-5 py-6 sm:grid-cols-2 lg:grid-cols-{{ min(4, $items->count()) }}">
        @foreach ($items as $item)
            <div class="flex items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="{{ $paths[$item['icon']] ?? $paths['check'] }}"/>
                    </svg>
                </span>
                <div>
                    <p class="text-sm font-semibold">{{ $item['title'] }}</p>
                    @if ($item['body'])
                        <p class="text-xs text-muted-foreground">{{ $item['body'] }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

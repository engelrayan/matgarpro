{{-- Why this product, in cards.

     A photo when the merchant has one — a picture of the benefit beats a
     sentence about it — and a drawn icon otherwise, tinted in the store's
     colour so a card without a photo still looks designed, not unfinished. --}}
@php
    $paths = [
        'sparkle' => 'M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z',
        'check' => 'M20 6 9 17l-5-5',
        'bolt' => 'M13 2 4 14h7l-1 8 9-12h-7z',
        'heart' => 'M12 20s-7-4.4-9-8.6A5 5 0 0 1 12 6a5 5 0 0 1 9 5.4C19 15.6 12 20 12 20z',
        'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z',
        'leaf' => 'M5 20c0-9 6-15 15-15 0 9-6 15-15 15zM5 20l7-7',
        'clock' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2',
        'gift' => 'M4 11h16v9H4zM3 7h18v4H3zM12 7v13M12 7c-2-3-5-3-5-1s3 1 5 1zm0 0c2-3 5-3 5-1s-3 1-5 1z',
    ];
    $items = collect($settings['items'])->filter(fn ($item) => filled($item['title']));
    $columns = (int) $settings['columns'];
@endphp

@if ($items->isNotEmpty())
<section class="px-4 py-10 md:px-5 md:py-14">
    <div class="mx-auto max-w-5xl">
        <div class="mx-auto max-w-xl text-center">
            <h2 class="text-balance text-[24px] font-extrabold leading-snug tracking-tight md:text-[32px]">{{ $settings['title'] }}</h2>
            @if (filled($settings['subtitle']))
                <p class="mt-2 text-pretty text-[16px] leading-relaxed text-muted-foreground">{{ $settings['subtitle'] }}</p>
            @endif
        </div>

        <div @class([
            'mt-7 grid gap-3 md:gap-4',
            'grid-cols-2' => $columns >= 2,
            'md:grid-cols-3' => $columns === 3,
            'md:grid-cols-4' => $columns === 4,
        ])>
            @foreach ($items as $item)
                <div class="ios-group flex flex-col overflow-hidden">
                    @if ($item['image'])
                        <img src="{{ \App\Support\Media::url($item['image']) }}" alt="" loading="lazy"
                             class="aspect-[4/3] w-full object-cover">
                    @endif
                    <div class="flex flex-1 flex-col p-4">
                        @unless ($item['image'])
                            <span class="mb-3 flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="{{ $paths[$item['icon']] ?? $paths['sparkle'] }}"/>
                                </svg>
                            </span>
                        @endunless
                        <p class="text-[16px] font-bold leading-snug">{{ $item['title'] }}</p>
                        @if (filled($item['body']))
                            <p class="mt-1 text-[14px] leading-relaxed text-muted-foreground">{{ $item['body'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if (filled($settings['button']) && isset($product))
            <div class="mt-7 text-center">
                <a href="#orderForm" class="ios-btn ios-btn-lg ios-btn-filled w-full md:w-auto md:px-10">{{ $settings['button'] }}</a>
            </div>
        @endif
    </div>
</section>
@endif

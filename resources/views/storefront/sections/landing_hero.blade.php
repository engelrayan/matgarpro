{{-- The promise, above the fold.

     What somebody who tapped an ad has to see before their thumb moves: the
     benefit in one line, the product, what it costs, and one button. The
     button scrolls to the order form rather than leaving the page — on a
     landing page the form is the only exit that pays.

     Everything falls back to the product itself, so a landing page the
     merchant has not written a word of is still a working hero. --}}
@php
    $image = $settings['image'] ? \App\Support\Media::url($settings['image']) : $product->primaryImage()?->url();
    $headline = $settings['headline'] ?: $product->name;
    $points = collect($settings['points'])->pluck('text')->filter();
    $bold = $settings['style'] === 'bold';
    $saving = $product->compare_at_price && $product->compare_at_price > $product->price
        ? (int) round((1 - $product->price / $product->compare_at_price) * 100)
        : 0;
@endphp

<section @class([
    'relative overflow-hidden',
    'bg-primary text-primary-foreground' => $bold,
    'bg-gradient-to-b from-primary/[0.08] via-primary/[0.03] to-transparent' => $settings['style'] === 'soft',
])>
    <div class="mx-auto grid max-w-5xl items-center gap-6 px-4 pb-8 pt-6 md:grid-cols-2 md:gap-10 md:px-5 md:py-14">
        <div class="order-2 text-center md:order-1 md:text-right">
            @if (filled($settings['badge']))
                <span @class([
                    'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[13px] font-semibold',
                    'bg-white/15' => $bold,
                    'bg-accent/15 text-foreground' => ! $bold,
                ])>
                    <span class="size-1.5 animate-pulse rounded-full bg-current"></span>
                    {{ $settings['badge'] }}
                </span>
            @endif

            <h2 class="mt-3 text-balance text-[30px] font-extrabold leading-[1.2] tracking-tight md:text-[44px]">
                {{ $headline }}
            </h2>

            @if (filled($settings['subheadline']))
                <p @class([
                    'mx-auto mt-3 max-w-md text-pretty text-[17px] leading-relaxed md:mx-0',
                    'text-primary-foreground/85' => $bold,
                    'text-muted-foreground' => ! $bold,
                ])>{{ $settings['subheadline'] }}</p>
            @endif

            @if ($points->isNotEmpty())
                <ul class="mx-auto mt-5 inline-flex flex-col gap-2 text-right md:mx-0">
                    @foreach ($points as $point)
                        <li class="flex items-center gap-2 text-[15px] font-medium">
                            <span @class([
                                'flex size-5 shrink-0 items-center justify-center rounded-full',
                                'bg-white/20' => $bold,
                                'bg-success/15 text-success' => ! $bold,
                            ])>
                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($settings['show_price'])
                <div class="mt-6 flex flex-wrap items-baseline justify-center gap-x-2 gap-y-1 md:justify-start">
                    <span class="tabular text-[34px] font-extrabold leading-none tracking-tight">{{ number_format((float) $product->price, 0) }}</span>
                    <span @class(['text-[15px] font-medium', 'text-muted-foreground' => ! $bold])>{{ $store->currency }}</span>
                    @if ($saving > 0)
                        <span @class(['tabular text-[17px] line-through', 'text-primary-foreground/60' => $bold, 'text-muted-foreground' => ! $bold])>
                            {{ number_format((float) $product->compare_at_price, 0) }}
                        </span>
                        <span class="rounded-md bg-destructive px-1.5 py-0.5 text-[12px] font-bold text-white">وفّر {{ $saving }}٪</span>
                    @endif
                </div>
            @endif

            <a href="#orderForm" @class([
                'ios-btn ios-btn-lg mt-6 w-full shadow-lg md:w-auto md:px-10',
                'bg-white text-primary' => $bold,
                'ios-btn-filled shadow-primary/25' => ! $bold,
            ])>
                {{ $settings['button'] ?: 'اطلب دلوقتي' }}
            </a>
            <p @class(['mt-2.5 text-[13px]', 'text-primary-foreground/75' => $bold, 'text-muted-foreground' => ! $bold])>
                الدفع عند الاستلام · من غير ما تدفع حاجة دلوقتي
            </p>
        </div>

        @if ($image)
            <div class="order-1 md:order-2">
                <div class="relative mx-auto aspect-square w-full max-w-sm md:max-w-none">
                    {{-- A soft glow in the store's colour behind the product,
                         so a photo on a white background does not sit on a
                         white page like a pasted sticker. --}}
                    <div @class([
                        'absolute inset-6 rounded-full blur-3xl',
                        'bg-white/25' => $bold,
                        'bg-primary/20' => ! $bold,
                    ])></div>
                    <img src="{{ $image }}" alt="{{ $product->name }}" fetchpriority="high"
                         class="relative size-full rounded-[28px] object-cover shadow-2xl shadow-black/10">
                </div>
            </div>
        @endif
    </div>
</section>

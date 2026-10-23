{{-- Hero slider — merchant images, or built from the catalogue.

     Plain JS and CSS transforms, no carousel library: this is the first paint
     of a page bought with ad money and a slider is not worth 40KB of it. Every
     slide is server-rendered so slide two is already in the HTML the instant a
     thumb swipes.

     Inset with rounded corners on a phone, like a card in the App Store's
     Today tab, rather than a full-bleed strip: the edge of the next block
     peeks out underneath and tells the customer the page keeps going. --}}
@php
    $slides = $data['slides'];
    $manual = ($settings['source'] ?? 'auto') === 'manual';
@endphp

@if ($slides->isNotEmpty())
<section class="mx-auto max-w-5xl px-4 pt-4 md:px-5 md:pt-6">
<div class="relative overflow-hidden rounded-[calc(var(--radius)+8px)] bg-card shadow-[0_0_0_0.5px_hsl(var(--foreground)/0.07),0_8px_30px_-12px_hsl(var(--foreground)/0.18)]"
     data-hero
     data-hero-autoplay="{{ $settings['autoplay'] ? $settings['interval'] * 1000 : 0 }}">
    <div class="flex transition-transform duration-700 ease-ios" data-hero-track>
        @foreach ($slides as $slide)
            @if ($manual)
                <a href="{{ $slide['link'] ?: '#' }}" class="relative w-full shrink-0">
                    <img src="{{ \App\Support\Media::url($slide['image']) }}"
                         alt="{{ $slide['title'] }}"
                         class="aspect-[16/9] w-full object-cover md:aspect-[16/7]"
                         @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif>

                    @if ($slide['title'] || $slide['subtitle'])
                        {{-- The scrim is what keeps white text readable over a
                             photo the merchant chose, whatever is in it. --}}
                        <div class="absolute inset-0 flex items-end bg-gradient-to-t from-black/65 via-black/20 to-transparent md:items-center md:bg-gradient-to-l">
                            <div class="w-full px-5 pb-8 md:px-10 md:pb-0">
                                <div class="max-w-md text-white">
                                    @if ($slide['title'])
                                        <h2 class="text-balance text-[24px] font-bold leading-snug drop-shadow md:text-4xl">
                                            {{ $slide['title'] }}
                                        </h2>
                                    @endif
                                    @if ($slide['subtitle'])
                                        <p class="mt-1.5 text-[15px] text-white/90 drop-shadow md:text-base">{{ $slide['subtitle'] }}</p>
                                    @endif
                                    @if ($slide['button_text'])
                                        <span class="ios-btn-md mt-4 bg-white px-6 text-black">{{ $slide['button_text'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </a>
            @else
                <a href="{{ route('storefront.product', $slide->slug) }}"
                   class="relative w-full shrink-0" aria-label="{{ $slide->name }}">
                    <div class="grid items-center md:grid-cols-2">
                        <div class="order-2 px-5 pb-10 pt-5 text-center md:order-1 md:px-12 md:py-14 md:text-start">
                            @if ($percent = $slide->discountPercent())
                                <span class="badge mb-3 bg-destructive/10 text-[13px] font-semibold text-destructive">وفّر {{ $percent }}%</span>
                            @endif

                            <h2 class="text-balance text-[24px] font-bold leading-snug md:text-4xl md:leading-tight">
                                {{ $slide->name }}
                            </h2>

                            <div class="mt-2.5 flex items-baseline justify-center gap-2 md:justify-start">
                                <span class="tabular text-[24px] font-bold tracking-tight md:text-3xl">
                                    {{ number_format((float) $slide->price, 2) }}
                                </span>
                                <span class="text-[13px] font-medium text-muted-foreground">{{ $store->currency }}</span>
                                @if ($slide->compare_at_price)
                                    <span class="tabular text-[15px] text-muted-foreground line-through decoration-muted-foreground/50">
                                        {{ number_format((float) $slide->compare_at_price, 2) }}
                                    </span>
                                @endif
                            </div>

                            <span class="ios-btn-md ios-btn-filled mt-5 px-8">اطلب دلوقتي</span>
                        </div>

                        <div class="order-1 bg-muted md:order-2 md:h-full">
                            <img src="{{ $slide->primaryImage()->url() }}"
                                 alt="{{ $slide->name }}"
                                 class="aspect-[4/3] w-full object-cover md:aspect-square md:h-full"
                                 {{-- Largest thing above the fold on slide one: it
                                      decides the LCP, and therefore the cost of
                                      every visit. --}}
                                 @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif
                                 width="600" height="600">
                        </div>
                    </div>
                </a>
            @endif
        @endforeach
    </div>

    @if ($slides->count() > 1)
        {{-- Arrows on desktop only. A phone swipes, and two grey circles
             sitting on the product photo cover the very thing being sold. --}}
        <button type="button" data-hero-prev aria-label="السابق"
                class="material absolute right-3 top-1/2 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full text-foreground shadow-e1 transition-transform active:scale-95 md:flex">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
        </button>
        <button type="button" data-hero-next aria-label="التالي"
                class="material absolute left-3 top-1/2 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full text-foreground shadow-e1 transition-transform active:scale-95 md:flex">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
        </button>

        {{-- iOS page control. Each dot is drawn small but sits in a 24px
             button, so it can actually be hit. --}}
        <div class="absolute inset-x-0 bottom-2 flex justify-center">
            @foreach ($slides as $slide)
                <button type="button" data-hero-dot="{{ $loop->index }}"
                        aria-label="شريحة {{ $loop->iteration }}"
                        class="group flex size-6 items-center justify-center">
                    <span class="size-[7px] rounded-full bg-foreground/20 transition-colors duration-300 group-data-[active]:bg-foreground/75"></span>
                </button>
            @endforeach
        </div>
    @endif
</div>
</section>

@once
@push('scripts')
<script>
// Scoped per hero element rather than to the document: a page may only have one
// hero today, but the section system means "only one" is a config value, not a
// guarantee the script gets to assume.
document.querySelectorAll('[data-hero]').forEach((hero) => {
    const track = hero.querySelector('[data-hero-track]');
    const dots = [...hero.querySelectorAll('[data-hero-dot]')];
    const count = track.children.length;
    if (count < 2) return;

    const every = parseInt(hero.dataset.heroAutoplay, 10) || 0;
    let index = 0;
    let timer;

    function go(next) {
        index = (next + count) % count;
        // RTL: slides advance to the right, so the track shifts positively.
        track.style.transform = `translateX(${index * 100}%)`;
        dots.forEach((dot, i) => dot.toggleAttribute('data-active', i === index));
    }

    function play() {
        stop();
        if (every > 0) timer = setInterval(() => go(index + 1), every);
    }

    function stop() {
        clearInterval(timer);
    }

    hero.querySelector('[data-hero-next]')?.addEventListener('click', (e) => { e.preventDefault(); go(index + 1); play(); });
    hero.querySelector('[data-hero-prev]')?.addEventListener('click', (e) => { e.preventDefault(); go(index - 1); play(); });
    dots.forEach((dot, i) => dot.addEventListener('click', (e) => { e.preventDefault(); go(i); play(); }));

    // Swipe. Most of this traffic is a thumb on a phone.
    let startX = null;
    track.addEventListener('touchstart', (e) => { startX = e.touches[0].clientX; stop(); }, { passive: true });
    track.addEventListener('touchend', (e) => {
        if (startX === null) return;
        const dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 40) go(index + (dx > 0 ? -1 : 1));
        startX = null;
        play();
    }, { passive: true });

    hero.addEventListener('mouseenter', stop);
    hero.addEventListener('mouseleave', play);
    // Nothing should animate on a tab nobody is looking at.
    document.addEventListener('visibilitychange', () => document.hidden ? stop() : play());

    go(0);
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) play();
});
</script>
@endpush
@endonce
@endif

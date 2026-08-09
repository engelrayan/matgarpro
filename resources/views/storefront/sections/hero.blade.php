{{-- Hero slider — merchant images, or built from the catalogue.

     Plain JS and CSS transforms, no carousel library: this is the first paint
     of a page bought with ad money and a slider is not worth 40KB of it. Every
     slide is server-rendered so slide two is already in the HTML the instant a
     thumb swipes. --}}
@php
    $slides = $data['slides'];
    $manual = ($settings['source'] ?? 'auto') === 'manual';
@endphp

@if ($slides->isNotEmpty())
<section class="relative overflow-hidden bg-muted"
         data-hero
         data-hero-autoplay="{{ $settings['autoplay'] ? $settings['interval'] * 1000 : 0 }}">
    <div class="flex transition-transform duration-500 ease-out" data-hero-track>
        @foreach ($slides as $slide)
            @if ($manual)
                <a href="{{ $slide['link'] ?: '#' }}" class="relative w-full shrink-0">
                    <img src="{{ \App\Support\Media::url($slide['image']) }}"
                         alt="{{ $slide['title'] }}"
                         class="aspect-[16/7] w-full object-cover"
                         @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif>

                    @if ($slide['title'] || $slide['subtitle'])
                        {{-- The scrim is what keeps white text readable over a
                             photo the merchant chose, whatever is in it. --}}
                        <div class="absolute inset-0 flex items-center bg-gradient-to-l from-black/60 via-black/25 to-transparent">
                            <div class="mx-auto w-full max-w-5xl px-6 md:px-10">
                                <div class="max-w-md text-white">
                                    @if ($slide['title'])
                                        <h2 class="text-balance text-2xl font-bold leading-snug drop-shadow md:text-4xl">
                                            {{ $slide['title'] }}
                                        </h2>
                                    @endif
                                    @if ($slide['subtitle'])
                                        <p class="mt-2 text-sm drop-shadow md:text-base">{{ $slide['subtitle'] }}</p>
                                    @endif
                                    @if ($slide['button_text'])
                                        <span class="btn-primary mt-5 px-7 py-3">{{ $slide['button_text'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </a>
            @else
                <a href="{{ route('storefront.product', $slide->slug) }}"
                   class="relative w-full shrink-0" aria-label="{{ $slide->name }}">
                    <div class="mx-auto grid max-w-5xl items-center gap-6 px-5 py-10 md:grid-cols-2 md:gap-10 md:py-16">
                        <div class="order-2 text-center md:order-1 md:text-right">
                            @if ($percent = $slide->discountPercent())
                                <span class="badge-danger mb-4">وفّر {{ $percent }}%</span>
                            @endif

                            <h2 class="text-balance text-2xl font-bold leading-snug tracking-tight md:text-4xl">
                                {{ $slide->name }}
                            </h2>

                            <div class="mt-4 flex items-baseline justify-center gap-3 md:justify-start">
                                <span class="tabular text-2xl font-bold text-primary md:text-3xl">
                                    {{ number_format((float) $slide->price, 2) }}
                                </span>
                                <span class="text-sm text-muted-foreground">{{ $store->currency }}</span>
                                @if ($slide->compare_at_price)
                                    <span class="tabular text-base text-muted-foreground line-through">
                                        {{ number_format((float) $slide->compare_at_price, 2) }}
                                    </span>
                                @endif
                            </div>

                            <span class="btn-primary mt-6 px-7 py-3">اطلب دلوقتي</span>
                        </div>

                        <div class="order-1 md:order-2">
                            <img src="{{ $slide->primaryImage()->url() }}"
                                 alt="{{ $slide->name }}"
                                 class="mx-auto aspect-square w-full max-w-sm rounded-[--radius] object-cover"
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
        <button type="button" data-hero-prev aria-label="السابق"
                class="absolute right-3 top-1/2 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-card/85 text-lg shadow-e1 backdrop-blur transition-colors hover:bg-card">‹</button>
        <button type="button" data-hero-next aria-label="التالي"
                class="absolute left-3 top-1/2 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-card/85 text-lg shadow-e1 backdrop-blur transition-colors hover:bg-card">›</button>

        <div class="absolute inset-x-0 bottom-4 flex justify-center gap-2">
            @foreach ($slides as $slide)
                <button type="button" data-hero-dot="{{ $loop->index }}"
                        aria-label="شريحة {{ $loop->iteration }}"
                        class="h-1.5 w-1.5 rounded-full bg-foreground/25 transition-all data-[active]:w-6 data-[active]:bg-primary"></button>
            @endforeach
        </div>
    @endif
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

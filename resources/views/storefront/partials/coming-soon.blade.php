{{-- A shop with nothing in it yet — shown as a shop being made.

     History, because both earlier versions were reasonable and both failed:
     two lines of grey text read as a dead site; grey skeleton product cards
     became the biggest thing on the page and read as broken. The lesson was
     "no grey boxes", and it still holds.

     What a first-time visitor should feel instead is that something is
     being made for them, right now — the way an AI tool shows its work while
     it thinks. So: the store's own colours drifting as light behind the
     page, its mark inside the same turning ring the platform uses wherever
     AI is working, three frosted cards of light slowly taking shape where
     the products will go, and one line that changes as the work goes on.

     It claims nothing it cannot know. No date, no "built by AI" — only that
     the shop is being prepared. The feeling comes from the motion, not from
     a promise.

     Pure CSS: this page is reached from ads like any other, and the effect
     must cost nothing to load. Under reduced motion every piece stands still
     in its finished position. --}}
<section class="cs relative isolate overflow-hidden rounded-[calc(var(--radius)+16px)]">
    {{-- Light, in the theme's own colours plus the AI sky and rose. --}}
    <div class="cs-aurora" aria-hidden="true"><i></i><i></i><i></i><i></i></div>

    {{-- Sparkles, scattered by hand rather than by a script. --}}
    <div class="cs-sparkles" aria-hidden="true">
        @foreach ([['8%', '18%', '1rem', '0s'], ['86%', '14%', '.75rem', '-1.2s'], ['16%', '62%', '.6rem', '-2.4s'], ['78%', '56%', '1.1rem', '-.6s'], ['48%', '8%', '.55rem', '-3s'], ['92%', '78%', '.7rem', '-1.8s'], ['4%', '86%', '.9rem', '-2.9s']] as [$x, $y, $size, $delay])
            <svg style="inset-inline-start: {{ $x }}; top: {{ $y }}; width: {{ $size }}; height: {{ $size }}; animation-delay: {{ $delay }}" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 0c.6 5.4 3.6 9.4 12 12-8.4 2.6-11.4 6.6-12 12-.6-5.4-3.6-9.4-12-12 8.4-2.6 11.4-6.6 12-12z"/>
            </svg>
        @endforeach
    </div>

    <div class="relative mx-auto max-w-3xl px-5 pb-16 pt-14 text-center md:pb-24 md:pt-20">

        {{-- The store's own mark, inside the turning ring. --}}
        <div class="cs-mark mx-auto w-fit">
            <div class="ai-ring rounded-[calc(var(--radius)+10px)]">
                <div class="flex size-24 items-center justify-center overflow-hidden rounded-[calc(var(--radius)+8.5px)] bg-card md:size-28">
                    @if ($logo = $store->logoUrl())
                        <img src="{{ $logo }}" alt="{{ $store->name }}" class="size-full object-cover">
                    @else
                        <span class="text-4xl font-bold text-primary md:text-5xl">{{ mb_substr($store->name, 0, 1) }}</span>
                    @endif
                </div>
            </div>
        </div>

        <p class="mt-8 inline-flex items-center gap-1.5 rounded-full bg-card/70 px-3.5 py-1.5 text-[13px] font-semibold shadow-[0_0_0_0.5px_hsl(var(--foreground)/0.1)] backdrop-blur">
            <svg class="cs-twinkle size-3.5 text-primary" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0c.6 5.4 3.6 9.4 12 12-8.4 2.6-11.4 6.6-12 12-.6-5.4-3.6-9.4-12-12 8.4-2.6 11.4-6.6 12-12z"/></svg>
            <span class="cs-shine">بيتجهّز دلوقتي</span>
        </p>

        <h1 class="mt-4 text-balance text-[2.25rem] font-bold leading-tight md:text-6xl">
            {{ $store->name }}
        </h1>

        <p class="mx-auto mt-4 max-w-md text-pretty text-[17px] leading-relaxed text-muted-foreground md:text-lg">
            {{ $store->description ?: 'بنجهّز المجموعة الأولى — منتجات مختارة بعناية، بأسعار تستاهل الانتظار.' }}
        </p>

        {{-- Where the products will be: light taking the shape of three
             cards. Frosted and colourful, never grey, and each breathes on
             its own beat so the row reads as work in progress, not a
             loading skeleton. --}}
        <div class="cs-cards mt-12 flex items-end justify-center" aria-hidden="true">
            @foreach ([0, 1, 2] as $i)
                <div class="cs-card {{ $i === 1 ? 'cs-card--lead ai-ring' : '' }}" style="--i: {{ $i }}">
                    <div class="cs-card__body">
                        <div class="cs-card__light"></div>
                        <span class="ai-shimmer mt-3 block h-2 w-4/5 rounded-full"></span>
                        <span class="ai-shimmer mt-2 block h-2 w-1/2 rounded-full"></span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- The work, narrated. Three lines on a loop, stacked in one grid
             cell so the height never jumps; a screen reader hears the first. --}}
        <p class="cs-status mx-auto mt-8 text-[15px] font-medium text-foreground/80">
            <span>بنختار أحلى المنتجات…</span>
            <span aria-hidden="true">بنرتّب الأقسام…</span>
            <span aria-hidden="true">بنحط اللمسات الأخيرة…</span>
        </p>

        <div class="mt-9 flex flex-wrap items-center justify-center gap-2 text-[14px]">
            @foreach ([
                ['M20 6 9 17l-5-5', 'الدفع عند الاستلام'],
                ['M5 12h14M12 5l7 7-7 7', 'شحن لكل المحافظات'],
                ['M3 12a9 9 0 1 0 9-9M3 12l4-4M3 12l4 4', 'استبدال ١٤ يوم'],
            ] as [$path, $label])
                <span class="inline-flex items-center gap-1.5 rounded-full bg-card/70 px-3.5 py-2 shadow-[0_0_0_0.5px_hsl(var(--foreground)/0.1)] backdrop-blur">
                    <svg class="size-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="{{ $path }}"/>
                    </svg>
                    {{ $label }}
                </span>
            @endforeach
        </div>
    </div>
</section>

@once
@push('head')
<style>
    .cs {
        background: hsl(var(--background));
        box-shadow: 0 0 0 .5px hsl(var(--foreground) / .06);
    }

    /* A faint grid where the light is strongest — a surface for the light to
       land on, not content. */
    .cs::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background-image:
            linear-gradient(hsl(var(--foreground) / .045) 1px, transparent 1px),
            linear-gradient(90deg, hsl(var(--foreground) / .045) 1px, transparent 1px);
        background-size: 3rem 3rem;
        mask-image: radial-gradient(60% 55% at 50% 40%, #000, transparent 80%);
    }

    /* ── Aurora ─────────────────────────────────────────────────────────
       Four blurred discs drifting on long, out-of-step loops, so the light
       never visibly repeats. Transforms only: the GPU moves them and the page
       never re-lays out. */
    .cs-aurora {
        position: absolute;
        inset: -25% -15%;
        z-index: -2;
        filter: blur(70px) saturate(130%);
        opacity: .7;
        pointer-events: none;
    }

    .cs-aurora i {
        position: absolute;
        width: min(36rem, 75vw);
        aspect-ratio: 1;
        border-radius: 50%;
        will-change: transform;
    }

    .cs-aurora i:nth-child(1) { top: 0;   inset-inline-end: 8%;  background: hsl(var(--primary) / .45); animation: cs-drift-a 19s ease-in-out infinite alternate; }
    .cs-aurora i:nth-child(2) { top: 14%; inset-inline-start: 2%; background: hsl(var(--accent) / .42);  animation: cs-drift-b 23s ease-in-out infinite alternate; }
    .cs-aurora i:nth-child(3) { top: 42%; inset-inline-end: 30%; width: min(26rem, 60vw); background: hsl(205 85% 66% / .32); animation: cs-drift-c 27s ease-in-out infinite alternate; }
    .cs-aurora i:nth-child(4) { top: 55%; inset-inline-start: 28%; width: min(22rem, 55vw); background: hsl(330 75% 72% / .26); animation: cs-drift-a 31s ease-in-out infinite alternate-reverse; }

    @keyframes cs-drift-a { to { transform: translate(-12%, 10%) scale(1.15); } }
    @keyframes cs-drift-b { to { transform: translate(14%, -8%) scale(.9); } }
    @keyframes cs-drift-c { to { transform: translate(-8%, -14%) scale(1.2); } }

    /* ── Sparkles ───────────────────────────────────────────────────────── */
    .cs-sparkles svg {
        position: absolute;
        color: hsl(var(--primary));
        opacity: 0;
        animation: cs-twinkle 3.6s ease-in-out infinite;
    }

    .cs-sparkles svg:nth-child(even) { color: hsl(var(--accent)); }

    .cs-twinkle { animation: cs-twinkle 2.8s ease-in-out infinite; }

    @keyframes cs-twinkle {
        0%, 100% { opacity: 0; transform: scale(.4) rotate(0deg); }
        50%      { opacity: .9; transform: scale(1) rotate(45deg); }
    }

    /* ── The mark ───────────────────────────────────────────────────────── */
    .cs-mark { animation: cs-float 5s ease-in-out infinite; }

    @keyframes cs-float {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-8px); }
    }

    /* ── Shimmering eyebrow ─────────────────────────────────────────────── */
    .cs-shine {
        background: linear-gradient(90deg, hsl(var(--primary)), hsl(var(--accent)), hsl(205 80% 55%), hsl(var(--primary)));
        background-size: 300% 100%;
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        animation: cs-shine 4s linear infinite;
    }

    @keyframes cs-shine { to { background-position: -300% 0; } }

    /* ── Cards of light ─────────────────────────────────────────────────── */
    .cs-card {
        --tilt: calc((var(--i) - 1) * 9deg);
        width: clamp(6.5rem, 26vw, 10rem);
        margin-inline: -.6rem;
        border-radius: calc(var(--radius) + 12px);
        transform: rotate(var(--tilt)) translateY(calc(var(--i) * 0px));
        animation: cs-card 6s ease-in-out infinite;
        animation-delay: calc(var(--i) * -2s);
    }

    .cs-card:not(.cs-card--lead) { margin-bottom: -.75rem; opacity: .9; }
    /* Both loops: its own float, and the AI ring's turn — one `animation`
       declaration would otherwise silence the other. */
    .cs-card--lead {
        z-index: 1;
        padding: 1.5px;
        animation: cs-card 6s ease-in-out infinite -2s, ai-turn 6s linear infinite;
    }

    .cs-card__body {
        border-radius: calc(var(--radius) + 10.5px);
        padding: .55rem .55rem .8rem;
        background: hsl(var(--card) / .62);
        -webkit-backdrop-filter: blur(18px) saturate(160%);
        backdrop-filter: blur(18px) saturate(160%);
        box-shadow:
            0 0 0 .5px hsl(var(--foreground) / .08),
            0 24px 50px -24px hsl(var(--primary) / .55);
    }

    .cs-card--lead .cs-card__body { background: hsl(var(--card) / .85); }

    /* Not a grey box: light gathering where a product photo will be. */
    .cs-card__light {
        aspect-ratio: 1;
        border-radius: calc(var(--radius) + 6px);
        background:
            radial-gradient(circle at 30% 28%, hsl(var(--primary) / .85), transparent 58%),
            radial-gradient(circle at 74% 70%, hsl(var(--accent) / .8), transparent 55%),
            radial-gradient(circle at 60% 20%, hsl(205 85% 66% / .6), transparent 45%),
            hsl(var(--muted));
        background-size: 160% 160%;
        animation: cs-gather 7s ease-in-out infinite alternate;
        animation-delay: calc(var(--i) * -2.3s);
    }

    @keyframes cs-card {
        0%, 100% { transform: rotate(var(--tilt)) translateY(0); }
        50%      { transform: rotate(var(--tilt)) translateY(-10px); }
    }

    @keyframes cs-gather {
        from { background-position: 0% 0%; filter: saturate(100%); }
        to   { background-position: 100% 100%; filter: saturate(140%) brightness(1.06); }
    }

    /* ── Narration ──────────────────────────────────────────────────────── */
    .cs-status { display: grid; }

    .cs-status > span {
        grid-area: 1 / 1;
        opacity: 0;
        animation: cs-say 9s ease-in-out infinite;
    }

    .cs-status > span:nth-child(2) { animation-delay: 3s; }
    .cs-status > span:nth-child(3) { animation-delay: 6s; }

    @keyframes cs-say {
        0%        { opacity: 0; transform: translateY(6px); }
        6%, 28%   { opacity: 1; transform: none; }
        34%, 100% { opacity: 0; transform: translateY(-6px); }
    }

    /* ── Still, for anyone who asked for less motion ────────────────────── */
    @media (prefers-reduced-motion: reduce) {
        .cs-aurora i, .cs-sparkles svg, .cs-twinkle, .cs-mark, .cs-shine,
        .cs-card, .cs-card__light, .cs-status > span { animation: none; }
        .cs-sparkles svg { opacity: .5; }
        .cs-status > span:first-child { opacity: 1; }
    }
</style>
@endpush
@endonce

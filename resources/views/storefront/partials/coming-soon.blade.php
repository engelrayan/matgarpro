{{-- A shop with nothing in it yet.

     This started as two lines of grey text, which read as a dead site. The
     first fix replaced them with skeleton product cards — and re-created the
     original problem from the other direction: four large grey rectangles
     became the biggest thing on the page, and a placeholder that dominates a
     layout looks broken rather than pending.

     So: no fake products at all. Deliberate empty space, the merchant's own
     brand colours, and one honest line of motion saying work is happening.
     Emptiness that was clearly designed reads as calm; emptiness filled with
     grey boxes reads as an error. --}}
<section class="cs relative overflow-hidden">
    <div class="relative mx-auto max-w-2xl px-5 py-20 text-center md:py-28">

        {{-- The store's own mark, lifted off the page. --}}
        <div class="cs-mark relative mx-auto flex size-24 items-center justify-center rounded-[--radius] bg-card shadow-e3">
            @if ($logo = $store->logoUrl())
                <img src="{{ $logo }}" alt="{{ $store->name }}" class="size-full rounded-[--radius] object-cover">
            @else
                <span class="text-4xl font-bold text-primary">{{ mb_substr($store->name, 0, 1) }}</span>
            @endif
        </div>

        <p class="mt-8 text-sm font-medium uppercase tracking-[0.2em] text-primary">قريباً</p>

        <h1 class="mt-3 text-balance text-3xl font-bold leading-tight tracking-tight md:text-5xl">
            {{ $store->name }}
        </h1>

        <p class="mx-auto mt-4 max-w-md text-pretty leading-relaxed text-muted-foreground md:text-lg">
            {{ $store->description ?: 'بنجهّز المجموعة الأولى — منتجات مختارة بعناية، بأسعار تستاهل الانتظار.' }}
        </p>

        {{-- One honest line of motion. It says "being prepared" without
             drawing four empty boxes to say it. --}}
        <div class="cs-bar mx-auto mt-10 h-1 w-56 overflow-hidden rounded-full bg-muted">
            <span class="block h-full w-1/3 rounded-full bg-primary"></span>
        </div>

        <div class="mt-10 flex flex-wrap items-center justify-center gap-2.5 text-sm">
            @foreach ([
                ['M20 6 9 17l-5-5', 'الدفع عند الاستلام'],
                ['M5 12h14M12 5l7 7-7 7', 'شحن لكل المحافظات'],
                ['M3 12a9 9 0 1 0 9-9M3 12l4-4M3 12l4 4', 'استبدال ١٤ يوم'],
            ] as [$path, $label])
                <span class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card/80 px-4 py-2 backdrop-blur">
                    <svg class="size-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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
    /*
    | The background is built from the theme's own two colours, so this page
    | belongs to the shop it is standing in for — a toy store and a watch
    | boutique get the same layout in completely different light.
    */
    .cs {
        background:
            radial-gradient(42rem 26rem at 78% -8%, hsl(var(--primary) / .13), transparent 62%),
            radial-gradient(34rem 22rem at 12% 4%, hsl(var(--accent) / .14), transparent 58%);
    }

    /* A faint grid, only where the wash is strongest. Gives the emptiness
       something to be, without becoming content. */
    .cs::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(hsl(var(--border) / .5) 1px, transparent 1px),
            linear-gradient(90deg, hsl(var(--border) / .5) 1px, transparent 1px);
        background-size: 3.5rem 3.5rem;
        mask-image: radial-gradient(28rem 18rem at 50% 25%, #000, transparent 75%);
        opacity: .6;
    }

    .cs-mark {
        animation: cs-float 4.5s ease-in-out infinite;
    }

    /* The glow sits behind the mark rather than on it, so a merchant's logo is
       never tinted by our decoration. */
    .cs-mark::after {
        content: '';
        position: absolute;
        inset: -35%;
        z-index: -1;
        border-radius: 999px;
        background: radial-gradient(closest-side, hsl(var(--primary) / .28), transparent);
    }

    @keyframes cs-float {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-8px); }
    }

    /* Indeterminate: it never reaches an end, because there is no end to
       report. A progress bar that fills to 100% and stops would be a promise
       about a date nobody made. */
    .cs-bar > span { animation: cs-run 1.9s ease-in-out infinite; }

    @keyframes cs-run {
        0%   { transform: translateX(120%); }
        100% { transform: translateX(-220%); }
    }

    @media (prefers-reduced-motion: reduce) {
        .cs-mark, .cs-bar > span { animation: none; }
        .cs-bar > span { width: 100%; opacity: .35; }
    }
</style>
@endpush
@endonce

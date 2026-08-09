{{-- A shop with nothing in it yet.

     Two lines of grey text is what this used to be, and it reads as broken —
     a visitor who lands on a blank page assumes the store is dead, not that it
     is new. That matters more than it sounds: a merchant sends this link to
     friends and family on day one, before a single product is uploaded, and
     that first impression is the one they judge the whole platform by.

     So the page says "being prepared", and looks it. Skeleton cards shimmer
     where products will be — the same shape they will actually take, so the
     visitor is being shown the plan rather than an apology. Nothing here
     pretends the products exist; the skeletons carry no names and no prices,
     and the heading says plainly that the shop is getting ready. --}}
<div class="py-14 md:py-20">
    <div class="mx-auto max-w-2xl px-2 text-center">

        {{-- The store's own mark, breathing. --}}
        <div class="cs-badge mx-auto flex size-20 items-center justify-center rounded-[--radius] bg-primary/10 text-primary">
            @if ($logo = $store->logoUrl())
                <img src="{{ $logo }}" alt="{{ $store->name }}" class="size-full rounded-[--radius] object-cover">
            @else
                <span class="text-3xl font-bold">{{ mb_substr($store->name, 0, 1) }}</span>
            @endif
        </div>

        <h2 class="mt-6 text-balance text-2xl font-bold tracking-tight md:text-3xl">
            {{ $store->name }} بيجهّز حاجات حلوة
        </h2>

        <p class="mx-auto mt-3 max-w-md text-pretty leading-relaxed text-muted-foreground">
            {{ $store->description ?: 'المجموعة الأولى في الطريق — منتجات مختارة بعناية، بأسعار تستاهل الانتظار.' }}
        </p>

        <div class="mt-6 flex flex-wrap items-center justify-center gap-2.5 text-sm">
            @foreach ([
                ['M20 6 9 17l-5-5', 'الدفع عند الاستلام'],
                ['M5 12h14M12 5l7 7-7 7', 'شحن لكل المحافظات'],
                ['M3 12a9 9 0 1 0 9-9M3 12l4-4M3 12l4 4', 'استبدال ١٤ يوم'],
            ] as [$path, $label])
                <span class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3.5 py-1.5">
                    <svg class="size-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="{{ $path }}"/>
                    </svg>
                    {{ $label }}
                </span>
            @endforeach
        </div>
    </div>

    {{-- Where the products will be. Same grid, same card proportions. --}}
    <div class="mx-auto mt-12 grid max-w-6xl grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
        @foreach (range(1, 4) as $i)
            <div class="cs-skeleton overflow-hidden rounded-[--radius] border border-border bg-card" style="--d: {{ $i * 0.12 }}s">
                <div class="cs-shimmer aspect-square bg-muted"></div>
                <div class="space-y-2 p-3.5">
                    <div class="cs-shimmer h-3 w-4/5 rounded bg-muted"></div>
                    <div class="cs-shimmer h-3 w-2/5 rounded bg-muted"></div>
                </div>
            </div>
        @endforeach
    </div>

    <p class="mt-10 text-center text-sm text-muted-foreground">
        تابعنا — أول ما ننزل المنتجات هتلاقيها هنا.
    </p>
</div>

@once
@push('head')
<style>
    /* One soft pulse on the mark, slow enough to read as "alive" rather than
       as something demanding attention. */
    .cs-badge { animation: cs-breathe 3.2s ease-in-out infinite; }

    @keyframes cs-breathe {
        0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 hsl(var(--primary) / .18); }
        50%      { transform: scale(1.04); box-shadow: 0 0 0 14px hsl(var(--primary) / 0); }
    }

    .cs-skeleton { animation: cs-rise .6s ease-out both; animation-delay: var(--d); }

    @keyframes cs-rise {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* The sweep runs across the placeholder the way a loading card does, which
       is the whole idea: "on its way", not "missing". */
    .cs-shimmer { position: relative; overflow: hidden; }

    .cs-shimmer::after {
        content: '';
        position: absolute;
        inset: 0;
        transform: translateX(-100%);
        background: linear-gradient(90deg, transparent, hsl(var(--card) / .85), transparent);
        animation: cs-sweep 1.8s ease-in-out infinite;
    }

    @keyframes cs-sweep {
        to { transform: translateX(100%); }
    }

    @media (prefers-reduced-motion: reduce) {
        .cs-badge, .cs-skeleton, .cs-shimmer::after { animation: none; }
        .cs-skeleton { opacity: 1; transform: none; }
    }
</style>
@endpush
@endonce

{{-- Live discounts, with a countdown to the merchant's own deadline.

     The timer only appears when `sale_ends_at` is set and still in the future.
     A countdown that resets on refresh is the oldest trick in the book, and it
     teaches customers to ignore every timer the store ever shows. --}}
@if ($deals->isNotEmpty())
@php($deadline = $deals->map->saleDeadline()->filter()->min())

<section class="py-8 md:py-12">
    <div class="mx-auto max-w-5xl px-4 md:px-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-[20px] font-bold leading-snug md:text-[22px]">عروض النهارده</h2>
                <p class="mt-0.5 text-[15px] text-muted-foreground">أسعار مخفّضة على مجموعة مختارة.</p>
            </div>

            @if ($deadline)
                <div class="flex items-center gap-1.5" data-countdown="{{ $deadline->toIso8601String() }}">
                    <span class="text-xs text-muted-foreground">ينتهي خلال</span>
                    @foreach ([['d', 'يوم'], ['h', 'ساعة'], ['m', 'دقيقة'], ['s', 'ثانية']] as [$unit, $label])
                        <div class="min-w-[46px] rounded-[10px] bg-foreground px-1.5 py-1.5 text-center text-background">
                            <span class="tabular block text-base font-bold leading-none" data-countdown-{{ $unit }}>--</span>
                            <span class="mt-0.5 block text-[10px] opacity-70">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-5">
            @foreach ($deals as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
</section>

@push('scripts')
<script>
(function () {
    const box = document.querySelector('[data-countdown]');
    if (!box) return;

    const target = new Date(box.dataset.countdown).getTime();
    const cells = {
        d: box.querySelector('[data-countdown-d]'),
        h: box.querySelector('[data-countdown-h]'),
        m: box.querySelector('[data-countdown-m]'),
        s: box.querySelector('[data-countdown-s]'),
    };

    const pad = (n) => String(n).padStart(2, '0');

    function tick() {
        const left = target - Date.now();

        if (left <= 0) {
            // The offer is over. Hide the timer rather than showing zeros —
            // and the server has already stopped listing expired deals, so a
            // reload will simply not show this block at all.
            box.remove();
            clearInterval(timer);
            return;
        }

        const s = Math.floor(left / 1000);
        cells.d.textContent = pad(Math.floor(s / 86400));
        cells.h.textContent = pad(Math.floor((s % 86400) / 3600));
        cells.m.textContent = pad(Math.floor((s % 3600) / 60));
        cells.s.textContent = pad(s % 60);
    }

    tick();
    const timer = setInterval(tick, 1000);
})();
</script>
@endpush
@endif

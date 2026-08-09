{{-- Live discounts, with a countdown to the merchant's own deadline.

     The timer only appears when `sale_ends_at` is set and still in the future.
     A countdown that resets on refresh is the oldest trick in the book, and it
     teaches customers to ignore every timer the store ever shows. --}}
@php
    $products = $data['products'];
    $deadline = $products->map->saleDeadline()->filter()->min();
@endphp

@if ($products->isNotEmpty())
<section class="border-y border-border bg-muted/40 py-10 md:py-14">
    <div class="mx-auto max-w-5xl px-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold tracking-tight md:text-2xl">{{ $settings['title'] }}</h2>
                <p class="mt-1 text-sm text-muted-foreground">أسعار مخفّضة على مجموعة مختارة.</p>
            </div>

            @if ($deadline)
                <div class="flex items-center gap-2" data-countdown="{{ $deadline->toIso8601String() }}">
                    <span class="text-xs text-muted-foreground">ينتهي خلال</span>
                    @foreach ([['d', 'يوم'], ['h', 'ساعة'], ['m', 'دقيقة'], ['s', 'ثانية']] as [$unit, $label])
                        <div class="min-w-12 rounded-[--radius] bg-card px-2 py-1.5 text-center shadow-e1">
                            <span class="tabular block text-base font-bold leading-none" data-countdown-{{ $unit }}>--</span>
                            <span class="mt-0.5 block text-[10px] text-muted-foreground">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach ($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
</section>

@include('storefront.partials.countdown-script')
@endif

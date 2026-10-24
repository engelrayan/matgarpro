{{-- Live discounts, with a countdown to the merchant's own deadline.

     The timer only appears when `sale_ends_at` is set and still in the future.
     A countdown that resets on refresh is the oldest trick in the book, and it
     teaches customers to ignore every timer the store ever shows. --}}
@php
    $products = $data['products'];
    $deadline = $products->map->saleDeadline()->filter()->min();
@endphp

@if ($products->isNotEmpty())
<section class="py-8 md:py-12">
    <div class="mx-auto max-w-5xl px-4 md:px-5">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-[20px] font-bold leading-snug md:text-[22px]">{{ $settings['title'] }}</h2>
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
            @foreach ($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
</section>

@include('storefront.partials.countdown-script')
@endif

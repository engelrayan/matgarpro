{{--
    One product card, shared by the home page and every category page.

    Kept as a partial rather than duplicated: the sold-out state and the
    discount badge are the two things most likely to be changed later, and a
    second copy is how a store ends up selling something it has none of.
--}}
@php
    $image = $product->primaryImage();
    $discount = $product->discountPercent();
    $soldOut = $product->track_stock && $product->availableStock() <= 0;
@endphp

<a href="{{ route('storefront.product', $product->slug) }}"
   class="group block overflow-hidden rounded-[--radius] border border-border bg-card transition-shadow hover:shadow-e2">

    <div class="relative aspect-square overflow-hidden bg-muted">
        @if ($image)
            <img
                src="{{ $image->url() }}"
                alt="{{ $image->alt ?: $product->name }}"
                class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.04]"
                loading="lazy"
                width="500" height="500"
            >
        @else
            <div class="flex size-full items-center justify-center text-xs text-muted-foreground">
                {{ $product->name }}
            </div>
        @endif

        @if ($soldOut)
            <div class="absolute inset-0 flex items-center justify-center bg-background/70">
                <span class="rounded-full bg-foreground px-3 py-1 text-xs font-medium text-background">خلص</span>
            </div>
        @elseif ($discount)
            <span class="absolute right-2 top-2 rounded-full bg-destructive px-2.5 py-1 text-xs font-semibold text-destructive-foreground">
                −{{ $discount }}%
            </span>
        @endif
    </div>

    <div class="p-3.5">
        <h3 class="line-clamp-2 text-sm font-medium leading-snug">{{ $product->name }}</h3>

        <div class="mt-2 flex items-baseline gap-2">
            <span class="tabular text-base font-bold text-primary">
                {{ number_format((float) $product->price, 2) }}
            </span>
            <span class="text-[11px] text-muted-foreground">{{ $store->currency }}</span>

            @if ($product->compare_at_price)
                <span class="tabular text-xs text-muted-foreground line-through">
                    {{ number_format((float) $product->compare_at_price, 2) }}
                </span>
            @endif
        </div>
    </div>
</a>

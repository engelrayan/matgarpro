{{--
    One product card, shared by every grid on the storefront.

    Kept as a partial rather than duplicated: the sold-out state and the
    discount badge are the two things most likely to be changed later, and a
    second copy is how a store ends up selling something it has none of.

    Four shapes, picked by the theme. They are not decoration — a card is the
    single most repeated object on a storefront, so its shape is most of what
    "this shop looks different" actually means:

      soft   rounded, bordered, lifts on hover — the friendly default
      sharp  no card chrome at all, image and text only, wide letter-spacing
             on the price. Reads expensive because nothing is competing.
      frame  the image sits inset inside a bordered box, catalogue-style
      full   the picture bleeds edge to edge and the text sits under it with
             no box. For shops where the photo IS the product.
--}}
@php
    $image = $product->primaryImage();
    $discount = $product->discountPercent();
    $soldOut = $product->track_stock && $product->availableStock() <= 0;
    $style = $theme['card'] ?? 'soft';
@endphp

<a
    href="{{ route('storefront.product', $product->slug) }}"
    @class([
        'group block',
        'overflow-hidden rounded-[--radius] border border-border bg-card transition-shadow hover:shadow-e2' => $style === 'soft',
        'overflow-hidden rounded-[--radius] border border-border bg-card p-2.5 transition-colors hover:border-primary/40' => $style === 'frame',
    ])
>
    <div @class([
        'relative overflow-hidden bg-muted',
        // A tall crop for `full`: a photo with room to breathe is the whole
        // point of that shape, and a square would waste it.
        'aspect-[4/5]' => $style === 'full',
        'aspect-square' => $style !== 'full',
        'rounded-[--radius]' => $style === 'frame' || $style === 'full',
    ])>
        @if ($image)
            <img
                src="{{ $image->url() }}"
                alt="{{ $image->alt ?: $product->name }}"
                class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.04]"
                loading="lazy"
                width="500" height="625"
            >
        @else
            <div class="flex size-full items-center justify-center px-3 text-center text-xs text-muted-foreground">
                {{ $product->name }}
            </div>
        @endif

        @if ($soldOut)
            <div class="absolute inset-0 flex items-center justify-center bg-background/70">
                <span class="rounded-full bg-foreground px-3 py-1 text-xs font-medium text-background">خلص</span>
            </div>
        @elseif ($discount)
            <span @class([
                'absolute right-2 top-2 text-xs font-semibold',
                // The quiet shapes get a quiet badge: a red pill shouting a
                // percentage undoes everything else the theme is doing.
                'rounded-full bg-destructive px-2.5 py-1 text-destructive-foreground' => in_array($style, ['soft', 'frame', 'full'], true),
                'bg-foreground px-2 py-0.5 tracking-wide text-background' => $style === 'sharp',
            ])>
                −{{ $discount }}%
            </span>
        @endif
    </div>

    <div @class([
        'p-3.5' => $style === 'soft',
        'px-0.5 pt-3' => $style === 'sharp',
        'px-1 pb-1 pt-3' => $style === 'frame',
        'pt-3' => $style === 'full',
    ])>
        <h3 @class([
            'line-clamp-2 leading-snug',
            'text-sm font-medium' => $style !== 'sharp',
            // Smaller, spaced and quiet — the price does the talking.
            'text-xs font-normal tracking-wide text-muted-foreground' => $style === 'sharp',
        ])>{{ $product->name }}</h3>

        <div @class([
            'mt-2 flex items-baseline gap-2',
            'mt-1.5' => $style === 'sharp',
        ])>
            <span @class([
                'tabular font-bold',
                'text-base text-primary' => $style !== 'sharp',
                'text-sm tracking-wide text-foreground' => $style === 'sharp',
            ])>{{ number_format((float) $product->price, 2) }}</span>

            <span class="text-[11px] text-muted-foreground">{{ $store->currency }}</span>

            @if ($product->compare_at_price)
                <span class="tabular text-xs text-muted-foreground line-through">
                    {{ number_format((float) $product->compare_at_price, 2) }}
                </span>
            @endif
        </div>
    </div>
</a>

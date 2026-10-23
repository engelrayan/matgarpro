{{--
    One product card, shared by every grid on the storefront.

    Kept as a partial rather than duplicated: the sold-out state and the
    discount badge are the two things most likely to be changed later, and a
    second copy is how a store ends up selling something it has none of.

    Four shapes, picked by the theme. They are not decoration — a card is the
    single most repeated object on a storefront, so its shape is most of what
    "this shop looks different" actually means:

      soft   rounded, a hairline edge instead of a border, settles on press —
             the friendly default, closest to an App Store card
      sharp  no card chrome at all, image and text only, wide letter-spacing
             on the price. Reads expensive because nothing is competing.
      frame  the image sits inset inside a bordered box, catalogue-style
      full   the picture bleeds edge to edge and the text sits under it with
             no box. For shops where the photo IS the product.

    Every shape presses like an iOS cell: it dims and settles a hair smaller
    under the thumb, so a tap is acknowledged before the next page arrives.
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
        'pressable group block',
        'ios-group' => $style === 'soft',
        'overflow-hidden rounded-[--radius] border border-border bg-card p-2 transition-colors hover:border-primary/40' => $style === 'frame',
    ])
>
    <div @class([
        'relative overflow-hidden bg-muted',
        // A tall crop for `full`: a photo with room to breathe is the whole
        // point of that shape, and a square would waste it.
        'aspect-[4/5]' => $style === 'full',
        'aspect-square' => $style !== 'full',
        'rounded-[calc(var(--radius)+4px)]' => $style === 'full',
        'rounded-[--radius]' => $style === 'frame',
    ])>
        @if ($image)
            <img
                src="{{ $image->url() }}"
                alt="{{ $image->alt ?: $product->name }}"
                class="size-full object-cover transition-transform duration-700 ease-ios md:group-hover:scale-[1.03]"
                loading="lazy"
                decoding="async"
                width="500" height="625"
            >
        @else
            <div class="flex size-full items-center justify-center px-3 text-center text-xs text-muted-foreground">
                {{ $product->name }}
            </div>
        @endif

        @if ($soldOut)
            <div class="absolute inset-0 flex items-center justify-center bg-background/55">
                <span class="material rounded-full px-3.5 py-1.5 text-[13px] font-semibold text-foreground">خلص</span>
            </div>
        @elseif ($discount)
            {{-- LTR inside, or the bidi algorithm prints "21%−". --}}
            <span @class([
                'tabular absolute start-2 top-2 text-[12px] font-bold leading-none',
                // The quiet shapes get a quiet badge: a red pill shouting a
                // percentage undoes everything else the theme is doing.
                'rounded-full bg-destructive px-2 py-1.5 text-destructive-foreground' => in_array($style, ['soft', 'frame', 'full'], true),
                'bg-foreground px-2 py-1 tracking-wide text-background' => $style === 'sharp',
            ]) dir="ltr">
                −{{ $discount }}%
            </span>
        @endif
    </div>

    <div @class([
        'px-3 pb-3 pt-2.5' => $style === 'soft',
        'px-0.5 pt-3' => $style === 'sharp',
        'px-1 pb-1 pt-2.5' => $style === 'frame',
        'px-0.5 pt-2.5' => $style === 'full',
    ])>
        <h3 @class([
            'line-clamp-2 leading-snug',
            'text-[14px] font-medium text-foreground' => $style !== 'sharp',
            // Smaller, spaced and quiet — the price does the talking.
            'text-xs font-normal tracking-wide text-muted-foreground' => $style === 'sharp',
        ])>{{ $product->name }}</h3>

        <div @class([
            'mt-1.5 flex flex-wrap items-baseline gap-x-1.5',
            'mt-1' => $style === 'sharp',
        ])>
            <span @class([
                'tabular font-bold',
                'text-[16px] tracking-tight text-foreground' => $style !== 'sharp',
                'text-sm tracking-wide text-foreground' => $style === 'sharp',
            ])>{{ number_format((float) $product->price, 2) }}</span>

            <span class="text-[11px] font-medium text-muted-foreground">{{ $store->currency }}</span>

            @if ($product->compare_at_price)
                <span class="tabular text-[12px] text-muted-foreground line-through decoration-muted-foreground/60">
                    {{ number_format((float) $product->compare_at_price, 2) }}
                </span>
            @endif
        </div>
    </div>
</a>

{{-- Quantity offers: "اشتري قطعتين بـ 400", one card each, picked like a
     radio — because it is one. Replaces the quantity stepper: the offer IS
     the quantity, and the server prices it from the offer row, never from
     anything on this form.

     With sizes or colours, the customer picks them for every piece in the
     offer; the rows are built by the page script from the template below,
     one per piece, whenever the offer changes. --}}
@php
    $reference = \App\Models\ProductOffer::referencePrice($product);
    $defaultOffer = $offers->firstWhere('is_default', true) ?? $offers->first();
@endphp

<fieldset>
    <legend class="mb-2.5 text-headline">اختار العرض المناسب</legend>

    <div class="space-y-2.5">
        @foreach ($offers as $offer)
            @php($saving = $offer->savingAgainst($product))
            <label class="flex cursor-pointer items-center gap-3 rounded-[calc(var(--radius)+4px)] bg-card p-4 shadow-[inset_0_0_0_1px_hsl(var(--foreground)/0.1)] transition-shadow has-[:checked]:bg-primary/[0.04] has-[:checked]:shadow-[inset_0_0_0_2px_hsl(var(--primary))]">
                <input type="radio" name="offer_id" value="{{ $offer->id }}" class="size-5 shrink-0 accent-[hsl(var(--primary))]"
                       @checked(old('offer_id') ? (int) old('offer_id') === $offer->id : $offer->is($defaultOffer))>

                <span class="min-w-0 flex-1">
                    @if ($offer->badge)
                        <span class="badge mb-1.5 bg-accent/15 text-[12px] font-semibold text-foreground">{{ $offer->badge }}</span>
                    @endif
                    <span class="block text-[16px] font-semibold leading-snug">{{ $offer->label() }}</span>
                    @if ($offer->quantity > 1)
                        <span class="mt-0.5 block text-footnote text-muted-foreground">
                            القطعة بـ <span class="tabular">{{ number_format((float) $offer->unit_price, 2) }}</span> {{ $store->currency }}
                        </span>
                    @endif
                    @if ($offer->note)
                        <span class="mt-0.5 block text-footnote text-muted-foreground">{{ $offer->note }}</span>
                    @endif

                    @if ($saving > 0 || $offer->free_shipping)
                        <span class="mt-2 flex flex-wrap gap-1.5">
                            @if ($saving > 0)
                                <span class="badge bg-destructive/10 text-[12px] font-semibold text-destructive">
                                    هتوفّر <span class="tabular">{{ number_format($saving) }}</span> {{ $store->currency }}
                                </span>
                            @endif
                            @if ($offer->free_shipping)
                                <span class="badge bg-success/10 text-[12px] font-semibold text-success">شحن مجاني</span>
                            @endif
                        </span>
                    @endif
                </span>

                <span class="shrink-0 text-left">
                    <span class="tabular block text-[18px] font-bold leading-tight">{{ number_format($offer->total(), 2) }}</span>
                    <span class="block text-[12px] font-medium text-muted-foreground">{{ $store->currency }}</span>
                    @if ($reference > (float) $offer->unit_price)
                        <span class="tabular block text-[13px] text-muted-foreground line-through decoration-muted-foreground/50">
                            {{ number_format($reference * $offer->quantity, 2) }}
                        </span>
                    @endif
                </span>
            </label>
        @endforeach
    </div>
</fieldset>

<input type="hidden" name="quantity" id="quantity" value="{{ $defaultOffer->quantity }}">

@if ($product->hasVariants())
    <div id="offerPieces" class="space-y-5"></div>

    <template id="pieceTemplate">
        <div class="group space-y-3" data-piece>
            <p class="text-headline group-data-[missing]:text-destructive" data-piece-title></p>
            <p class="hidden text-[13px] text-destructive group-data-[missing]:block">اختار {{ collect($product->options)->pluck('name')->implode(' و') }} للقطعة دي.</p>
            @foreach ($product->options ?? [] as $option)
                <div>
                    <p class="mb-2 text-[14px] text-muted-foreground">{{ $option['name'] }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($option['values'] as $value)
                            <button type="button" class="piece-opt ios-chip" aria-pressed="false"
                                    data-option="{{ $option['name'] }}" data-value="{{ $value }}">{{ $value }}</button>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <input type="hidden" name="pieces[]" value="" data-piece-variant>
        </div>
    </template>
@endif

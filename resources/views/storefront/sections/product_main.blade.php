{{-- The product itself: gallery, price, options and the order form.

     Locked in the builder — a product page without this is a page that cannot
     sell. The merchant may move it up or down; they cannot remove it.

     `$productSettings` is the product's OWN page settings (button wording,
     form-before-description, sticky bar). `$settings` is this section's. The
     two are deliberately separate: the first differs per product, the second
     is the same on every product page in the shop. --}}
@php
    $images = $product->images;
    $saving = $product->compare_at_price && (float) $product->compare_at_price > (float) $product->price
        ? (float) $product->compare_at_price - (float) $product->price
        : 0;

    $checkoutFields = $store->enabledCheckoutFields();

    // Delivery priced by governorate, when the merchant has switched it on.
    // Only governorates the store delivers to are offered; the price rides on
    // each option so the total can update without a request.
    $shippingRates = \App\Services\Shipping\ShippingRates::for($store);
    $shippingOn = $shippingRates->enabled();
    $governorateOptions = $shippingOn
        ? $shippingRates->openGovernorates()
        : array_fill_keys((array) config('egypt.governorates'), null);

    // Errors that belong to a field are shown on that field. Anything else —
    // a sold-out variant, an expired form — still needs saying, at the top.
    $looseErrors = collect($errors->getMessages())
        ->except(array_keys($checkoutFields))
        ->flatten();

    // What the phone's autofill knows each field as. One tap fills the whole
    // form for a returning iPhone customer — the cheapest conversion there is.
    $autocomplete = [
        'customer_name' => 'name',
        'customer_phone' => 'tel',
        'customer_phone_alt' => 'tel',
        'customer_email' => 'email',
        'governorate' => 'address-level1',
        'city' => 'address-level2',
        'address' => 'street-address',
    ];
@endphp

<div class="mx-auto max-w-5xl md:px-5 md:py-8">
    <div class="grid md:grid-cols-2 md:gap-12">

        {{-- ── Gallery ──────────────────────────────────────────────────────

             Edge to edge on a phone and swiped with the thumb, the way Photos
             and every shopping app on the phone already work. Plain CSS scroll
             snapping — no carousel library on the page the ads pay for. Every
             image is in the HTML, so the second one is already there when the
             thumb arrives; only the first is fetched eagerly. --}}
        <div class="md:sticky md:top-24 md:self-start">
            @if ($images->isNotEmpty())
                <div class="relative">
                    <div class="scrollbar-none flex snap-x snap-mandatory overflow-x-auto overscroll-x-contain bg-muted md:rounded-[calc(var(--radius)+6px)] md:shadow-[0_0_0_0.5px_hsl(var(--foreground)/0.08)]"
                         data-gallery>
                        @foreach ($images as $image)
                            <img
                                src="{{ $image->url() }}"
                                alt="{{ $image->alt ?: $product->name }}"
                                class="aspect-square w-full shrink-0 snap-center object-cover"
                                width="800" height="800"
                                decoding="async"
                                data-slide="{{ $loop->index }}"
                                {{-- The first image is the largest thing above the
                                     fold; it decides the LCP and therefore the ad
                                     cost per visit. --}}
                                @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif
                            >
                        @endforeach
                    </div>

                    @if ($images->count() > 1)
                        {{-- Page control, as on the home screen. On a material
                             capsule so it reads over any photo. --}}
                        <div class="material pointer-events-none absolute bottom-3 left-1/2 flex -translate-x-1/2 items-center gap-1.5 rounded-full px-2.5 py-1.5 md:hidden" aria-hidden="true">
                            @foreach ($images as $image)
                                <span class="size-1.5 rounded-full bg-foreground/25 transition-colors duration-300 data-[active]:bg-foreground/80"
                                      data-dot="{{ $loop->index }}" @if ($loop->first) data-active @endif></span>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($images->count() > 1)
                    <div class="mt-3 hidden grid-cols-5 gap-2 md:grid">
                        @foreach ($images as $image)
                            <button
                                type="button"
                                class="thumb pressable overflow-hidden rounded-[--radius] opacity-60 ring-primary ring-offset-2 ring-offset-background transition-opacity hover:opacity-100 data-[active]:opacity-100 data-[active]:ring-2"
                                data-thumb="{{ $loop->index }}"
                                aria-label="صورة {{ $loop->iteration }}"
                                @if ($loop->first) data-active @endif
                            >
                                <img src="{{ $image->url() }}" alt="" class="aspect-square w-full object-cover" loading="lazy" width="160" height="160">
                            </button>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="aspect-square bg-muted md:rounded-[calc(var(--radius)+6px)]"></div>
            @endif

            {{-- The product's own video, when the merchant added one.

                 Under the gallery rather than as a slide inside it: a customer
                 flicking through photos is comparing angles, and a frame that
                 suddenly plays sound is not the same kind of thing. Here it is
                 a deliberate second look, right where somebody who is still
                 deciding will scroll to. --}}
            @if ($videoId = $product->videoId())
                <div class="mt-3 px-4 md:px-0">
                    @include('storefront.partials.youtube', ['videoId' => $videoId])
                    <p class="mt-2 text-center text-footnote text-muted-foreground">شوف المنتج بالفيديو</p>
                </div>
            @endif
        </div>

        {{-- ── Details + order form ─────────────────────────────────────── --}}
        <div class="px-4 pb-8 pt-5 md:px-0 md:pt-0">
            @if (filled($settings['badge']))
                <span class="badge mb-2.5 bg-accent/15 text-[13px] font-semibold text-foreground">{{ $settings['badge'] }}</span>
            @endif

            <h1 class="text-title-2 md:text-[30px] md:leading-[1.3]">{{ $product->name }}</h1>

            <div class="mt-3 flex flex-wrap items-baseline gap-x-2 gap-y-2">
                <span id="price" class="tabular text-[30px] font-bold leading-none tracking-tight text-foreground">
                    {{ number_format((float) $product->price, 2) }}
                </span>
                <span class="text-[15px] font-medium text-muted-foreground">{{ $store->currency }}</span>

                @if ($product->compare_at_price)
                    <span class="tabular text-[17px] text-muted-foreground line-through decoration-muted-foreground/50">
                        {{ number_format((float) $product->compare_at_price, 2) }}
                    </span>
                @endif
            </div>

            @if ($saving > 0 || $productSettings['free_shipping'])
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($saving > 0)
                        <span class="badge bg-destructive/10 py-1.5 text-[13px] font-semibold text-destructive">
                            خصم {{ $product->discountPercent() }}% · هتوفّر <span class="tabular">{{ number_format($saving) }}</span> {{ $store->currency }}
                        </span>
                    @endif

                    @if ($productSettings['free_shipping'])
                        <span class="badge bg-success/10 py-1.5 text-[13px] font-semibold text-success">
                            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            شحن مجاني
                        </span>
                    @endif
                </div>
            @endif

            {{-- The merchant chooses the order. A cheap impulse buy converts
                 better with the form first; anything that needs convincing
                 wants the pitch first. --}}
            @unless ($productSettings['form_before_description'])
                @if ($product->description)
                    {{-- Unescaped: stored already sanitised by HtmlSanitizer,
                         which strips every tag and attribute outside its
                         allow-list. --}}
                    <div class="prose-description mt-5 text-callout text-muted-foreground">{!! $product->description !!}</div>
                @endif
            @endunless

            @if ($looseErrors->isNotEmpty())
                <div class="mt-6 flex gap-3 rounded-[calc(var(--radius)+4px)] bg-destructive/10 p-4 text-[15px] text-destructive" role="alert">
                    <svg class="mt-0.5 size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.01"/></svg>
                    <div class="space-y-1">
                        @foreach ($looseErrors as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('storefront.checkout') }}" class="mt-7 space-y-6" id="orderForm">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="variant_id" id="variantId" value="">

                {{-- Two traps for form-filling scripts. An order bills the
                     merchant the moment it is created, so a flood of fake ones
                     is a way to drain a shop's balance until it stops selling.

                     Clipped to nothing rather than `display:none` — some bots
                     skip hidden inputs, and a real customer never reaches this
                     one either way: no tab stop, no autocomplete, hidden from
                     screen readers.

                     Clipped IN PLACE, never pushed off-screen: this page is
                     RTL, and in RTL a box at `left:-9999px` sits inside the
                     scrollable area — it made the page ten thousand pixels
                     wide and phones painted the first screen blank. --}}
                <div aria-hidden="true" style="position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap">
                    <label for="confirm_url">لا تكتب هنا</label>
                    <input type="text" id="confirm_url" name="confirm_url" tabindex="-1" autocomplete="off" value="">
                </div>

                {{-- When the form was opened, encrypted so it cannot be
                     back-dated. A human needs seconds to fill this in; a
                     script posts instantly. --}}
                <input type="hidden" name="form_opened_at" value="{{ Crypt::encryptString((string) time()) }}">

                {{-- Options ------------------------------------------------ --}}
                @foreach ($product->options ?? [] as $option)
                    <fieldset>
                        <legend class="mb-2.5 text-headline">{{ $option['name'] }}</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($option['values'] as $value)
                                <button
                                    type="button"
                                    class="opt ios-chip"
                                    aria-pressed="false"
                                    data-option="{{ $option['name'] }}"
                                    data-value="{{ $value }}"
                                >{{ $value }}</button>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                {{-- Quantity ----------------------------------------------- --}}
                <div class="ios-group">
                    <div class="ios-row justify-between">
                        <label class="text-[17px]" for="quantity">الكمية</label>
                        <div class="ios-stepper">
                            <button type="button" class="qty" data-step="-1" aria-label="أقل">−</button>
                            <input id="quantity" name="quantity" type="text" inputmode="numeric" value="1" aria-live="polite"
                                   class="tabular w-10 border-0 bg-transparent p-0 text-center text-[17px] font-semibold focus:outline-none focus:ring-0">
                            <button type="button" class="qty" data-step="1" aria-label="أكتر">+</button>
                        </div>
                    </div>
                </div>

                {{-- Rendered from the store's own form settings, in the order
                     the merchant chose. Every extra field on a COD form costs
                     orders, so the merchant owns that trade-off — but the name
                     and the phone are locked on, because a parcel nobody can
                     confirm is worth less than no order at all.

                     One grouped card, as in an iOS address sheet: a short label
                     over a borderless field, the focused row ringed in the
                     theme colour. --}}
                <div>
                    <h2 class="ios-caption mb-2 !px-1 text-[15px] font-semibold text-foreground">بيانات التوصيل</h2>

                    <div class="ios-group">
                        @foreach ($checkoutFields as $key => $field)
                            <div @class(['ios-field-row', 'has-error' => $errors->has($key)])>
                                <label class="ios-field-label" for="{{ $key }}">
                                    {{ $field['label'] }}
                                    @unless ($field['required'])
                                        <span class="font-normal opacity-75">— اختياري</span>
                                    @endunless
                                </label>

                                @if ($key === 'governorate')
                                    <select id="{{ $key }}" name="{{ $key }}" class="ios-input" autocomplete="address-level1" @required($field['required'])>
                                        <option value="">اختار المحافظة</option>
                                        @foreach ($governorateOptions as $gov => $shippingPrice)
                                            <option value="{{ $gov }}" @if ($shippingPrice !== null) data-shipping="{{ $shippingPrice }}" @endif @selected(old('governorate') === $gov)>{{ $gov }}</option>
                                        @endforeach
                                    </select>

                                @elseif (in_array($key, ['address', 'note'], true))
                                    <textarea id="{{ $key }}" name="{{ $key }}" class="ios-input" rows="2"
                                              placeholder="{{ $field['placeholder'] }}"
                                              @if (isset($autocomplete[$key])) autocomplete="{{ $autocomplete[$key] }}" @endif
                                              @required($field['required'])>{{ old($key) }}</textarea>

                                @else
                                    <input
                                        id="{{ $key }}"
                                        name="{{ $key }}"
                                        @class(['ios-input', 'text-right' => str_contains($key, 'phone') || $key === 'customer_email'])
                                        value="{{ old($key) }}"
                                        placeholder="{{ $field['placeholder'] }}"
                                        @required($field['required'])
                                        @if (isset($autocomplete[$key])) autocomplete="{{ $autocomplete[$key] }}" @endif
                                        {{-- `tel` brings up the dial pad on every phone; the
                                             number reads left to right inside an RTL row. --}}
                                        @if (str_contains($key, 'phone')) type="tel" dir="ltr" inputmode="tel" @endif
                                        @if ($key === 'customer_email') type="email" dir="ltr" @endif
                                    >
                                @endif

                                @error($key)
                                    <p class="text-[13px] text-destructive">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Summary ------------------------------------------------ --}}
                <div class="ios-group">
                    @if ($shippingOn)
                        {{-- Said before the total, not discovered on the waybill:
                             a COD parcel refused at the door over a fee the
                             customer never saw costs the merchant both ways. --}}
                        <div class="ios-row justify-between">
                            <span class="text-[15px] text-muted-foreground">الشحن</span>
                            <span id="shippingAmount" class="tabular text-[15px] font-medium"
                                  data-choose="اختار المحافظة" data-free="مجاني">
                                {{ $productSettings['free_shipping'] ? 'مجاني' : 'اختار المحافظة' }}
                            </span>
                        </div>
                    @endif
                    <div class="ios-row justify-between">
                        <span class="text-[17px] text-muted-foreground">الإجمالي</span>
                        <span id="total" class="tabular text-[20px] font-bold tracking-tight">
                            {{ number_format((float) $product->price, 2) }} {{ $store->currency }}
                        </span>
                    </div>
                    <div class="ios-row justify-between">
                        <span class="text-[15px] text-muted-foreground">طريقة الدفع</span>
                        <span class="flex items-center gap-1.5 text-[15px] font-medium">
                            <svg class="size-[18px] text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="6" width="19" height="12" rx="2.5"/><circle cx="12" cy="12" r="2.5"/></svg>
                            الدفع عند الاستلام
                        </span>
                    </div>
                </div>

                <div>
                    <button type="submit" class="ios-btn-lg ios-btn-filled w-full shadow-[0_8px_24px_-8px_hsl(var(--primary)/0.55)]">
                        {{ $productSettings['buy_button_text'] }}
                    </button>

                    {{-- The single biggest objection on a cash-on-delivery form,
                         answered right under the button that raises it. --}}
                    <p class="mt-3 flex items-center justify-center gap-1.5 text-footnote text-muted-foreground">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                        مش هتدفع حاجة دلوقتي — بتدفع لما الطلب يوصلك
                    </p>
                </div>
            </form>

            @if ($productSettings['form_before_description'] && $product->description)
                {{-- Shown here only when there is no separate "وصف المنتج"
                     section on the page — otherwise the customer reads the
                     same paragraph twice. --}}
                @unless ($hasDescriptionSection ?? false)
                    <div class="prose-description mt-8 text-callout text-muted-foreground">{!! $product->description !!}</div>
                @endunless
            @endif
        </div>
    </div>
</div>

@if ($productSettings['sticky_buy_bar'])
    {{-- Pinned buy bar, phones only. The form sits under a tall image, and a
         customer who has to scroll back up to buy frequently does not. Hidden
         once the real button is on screen so there are never two at once.

         An iOS toolbar: translucent, a hairline on top, and padded clear of
         the home indicator. --}}
    <div id="stickyBar" class="material hairline-t pb-safe fixed inset-x-0 bottom-0 z-40 hidden px-4 pt-3 md:!hidden">
        <div class="mx-auto flex max-w-5xl items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="truncate text-footnote text-muted-foreground">{{ $product->name }}</p>
                <p class="tabular text-[17px] font-bold leading-tight tracking-tight">
                    <span id="stickyTotal">{{ number_format((float) $product->price, 2) }}</span>
                    <span class="text-[13px] font-medium text-muted-foreground">{{ $store->currency }}</span>
                </p>
            </div>
            <button type="submit" form="orderForm" class="ios-btn-md ios-btn-filled shrink-0 px-7">
                {{ $productSettings['buy_button_text'] }}
            </button>
        </div>
    </div>

    {{-- Room for the bar so it never covers the last field. --}}
    <div class="h-24 md:hidden"></div>
@endif

@include('storefront.partials.product-scripts')

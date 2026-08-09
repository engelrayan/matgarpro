{{-- The product itself: gallery, price, options and the order form.

     Locked in the builder — a product page without this is a page that cannot
     sell. The merchant may move it up or down; they cannot remove it.

     `$productSettings` is the product's OWN page settings (button wording,
     form-before-description, sticky bar). `$settings` is this section's. The
     two are deliberately separate: the first differs per product, the second
     is the same on every product page in the shop. --}}
<div class="mx-auto max-w-5xl px-5 py-8">
    <div class="grid gap-8 md:grid-cols-2 md:gap-12">

        {{-- ── Gallery ──────────────────────────────────────────────────── --}}
        <div>
            @if ($product->images->isNotEmpty())
                <div class="overflow-hidden rounded-2xl border border-border bg-card">
                    <img
                        id="mainImage"
                        src="{{ $product->images->first()->url() }}"
                        alt="{{ $product->images->first()->alt ?: $product->name }}"
                        class="aspect-square w-full object-cover"
                        {{-- The first image is the largest thing above the fold; it
                             decides the LCP and therefore the ad cost per visit. --}}
                        fetchpriority="high"
                        width="800" height="800"
                    >
                </div>

                @if ($product->images->count() > 1)
                    <div class="mt-3 grid grid-cols-5 gap-2">
                        @foreach ($product->images as $image)
                            <button
                                type="button"
                                class="thumb overflow-hidden rounded-xl border-2 {{ $loop->first ? 'border-primary' : 'border-transparent' }}"
                                data-src="{{ $image->url() }}"
                            >
                                <img src="{{ $image->url() }}" alt="" class="aspect-square w-full object-cover" loading="lazy" width="160" height="160">
                            </button>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="aspect-square rounded-2xl bg-muted"></div>
            @endif
        </div>

        {{-- ── Details + order form ─────────────────────────────────────── --}}
        <div>
            @if (filled($settings['badge']))
                <span class="badge-gold mb-3">{{ $settings['badge'] }}</span>
            @endif

            <h1 class="text-2xl font-bold leading-snug tracking-tight md:text-3xl">{{ $product->name }}</h1>

            <div class="mt-4 flex flex-wrap items-baseline gap-3">
                <span id="price" class="tabular text-3xl font-bold text-primary">
                    {{ number_format((float) $product->price, 2) }}
                </span>
                <span class="text-sm text-muted-foreground">{{ $store->currency }}</span>

                @if ($product->compare_at_price)
                    <span class="tabular text-base text-muted-foreground line-through">
                        {{ number_format((float) $product->compare_at_price, 2) }}
                    </span>
                    <span class="badge-danger">خصم {{ $product->discountPercent() }}%</span>
                @endif

                @if ($productSettings['free_shipping'])
                    <span class="badge-success">شحن مجاني</span>
                @endif
            </div>

            {{-- The merchant chooses the order. A cheap impulse buy converts
                 better with the form first; anything that needs convincing
                 wants the pitch first. --}}
            @unless ($productSettings['form_before_description'])
                @if ($product->description)
                    {{-- Unescaped: stored already sanitised by HtmlSanitizer,
                         which strips every tag and attribute outside its
                         allow-list. --}}
                    <div class="prose-description mt-5 text-sm leading-relaxed text-muted-foreground">{!! $product->description !!}</div>
                @endif
            @endunless

            @if ($errors->any())
                <div class="mt-6 rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('storefront.checkout') }}" class="mt-8 space-y-5" id="orderForm">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="hidden" name="variant_id" id="variantId" value="">

                {{-- Options ------------------------------------------------ --}}
                @foreach ($product->options ?? [] as $option)
                    <div>
                        <label class="field-label">{{ $option['name'] }}</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($option['values'] as $value)
                                <button
                                    type="button"
                                    class="opt rounded-xl border border-border px-4 py-2 text-sm transition-colors hover:border-primary"
                                    data-option="{{ $option['name'] }}"
                                    data-value="{{ $value }}"
                                >{{ $value }}</button>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                {{-- Quantity ----------------------------------------------- --}}
                <div>
                    <label class="field-label" for="quantity">الكمية</label>
                    <div class="flex w-32 items-center rounded-xl border border-input bg-card">
                        <button type="button" class="qty px-3.5 py-2 text-lg leading-none" data-step="-1" aria-label="أقل">−</button>
                        <input id="quantity" name="quantity" type="text" inputmode="numeric" value="1"
                               class="tabular w-full border-0 bg-transparent p-0 text-center text-sm focus:outline-none">
                        <button type="button" class="qty px-3.5 py-2 text-lg leading-none" data-step="1" aria-label="أكتر">+</button>
                    </div>
                </div>

                <hr class="border-border">

                {{-- Rendered from the store's own form settings, in the order
                     the merchant chose. Every extra field on a COD form costs
                     orders, so the merchant owns that trade-off — but the name
                     and the phone are locked on, because a parcel nobody can
                     confirm is worth less than no order at all. --}}
                <div class="space-y-4">
                    @foreach ($store->enabledCheckoutFields() as $key => $field)
                        <div>
                            <label class="field-label" for="{{ $key }}">
                                {{ $field['label'] }}
                                @unless ($field['required'])
                                    <span class="font-normal text-muted-foreground">— اختياري</span>
                                @endunless
                            </label>

                            @if ($key === 'governorate')
                                <select id="{{ $key }}" name="{{ $key }}" class="field" @required($field['required'])>
                                    <option value="">اختار المحافظة</option>
                                    @foreach (config('egypt.governorates') as $gov)
                                        <option value="{{ $gov }}" @selected(old('governorate') === $gov)>{{ $gov }}</option>
                                    @endforeach
                                </select>

                            @elseif (in_array($key, ['address', 'note'], true))
                                <textarea id="{{ $key }}" name="{{ $key }}" class="field" rows="3"
                                          placeholder="{{ $field['placeholder'] }}"
                                          @required($field['required'])>{{ old($key) }}</textarea>

                            @else
                                <input
                                    id="{{ $key }}"
                                    name="{{ $key }}"
                                    class="field"
                                    value="{{ old($key) }}"
                                    placeholder="{{ $field['placeholder'] }}"
                                    @required($field['required'])
                                    @if (str_contains($key, 'phone')) dir="ltr" inputmode="tel" autocomplete="tel" @endif
                                    @if ($key === 'customer_email') type="email" dir="ltr" autocomplete="email" @endif
                                    @if ($key === 'customer_name') autocomplete="name" @endif
                                >
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="rounded-xl bg-muted/60 p-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">الإجمالي</span>
                        <span id="total" class="tabular text-lg font-bold">
                            {{ number_format((float) $product->price, 2) }} {{ $store->currency }}
                        </span>
                    </div>
                    <p class="mt-1.5 text-xs text-muted-foreground">الدفع عند الاستلام</p>
                </div>

                <button type="submit" class="btn-primary sheen w-full py-3.5 text-base">
                    {{ $productSettings['buy_button_text'] }}
                </button>
            </form>

            @if ($productSettings['form_before_description'] && $product->description)
                {{-- Shown here only when there is no separate "وصف المنتج"
                     section on the page — otherwise the customer reads the
                     same paragraph twice. --}}
                @unless ($hasDescriptionSection ?? false)
                    <div class="prose-description mt-8 text-sm leading-relaxed text-muted-foreground">{!! $product->description !!}</div>
                @endunless
            @endif
        </div>
    </div>
</div>

@if ($productSettings['sticky_buy_bar'])
    {{-- Pinned buy bar, phones only. The form sits under a tall image, and a
         customer who has to scroll back up to buy frequently does not. Hidden
         once the real button is on screen so there are never two at once. --}}
    <div id="stickyBar" class="glass fixed inset-x-0 bottom-0 z-40 hidden border-t px-4 py-3 md:!hidden">
        <div class="mx-auto flex max-w-5xl items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs text-muted-foreground">{{ $product->name }}</p>
                <p class="tabular text-sm font-bold">
                    <span id="stickyTotal">{{ number_format((float) $product->price, 2) }}</span>
                    {{ $store->currency }}
                </p>
            </div>
            <button type="submit" form="orderForm" class="btn-primary shrink-0 px-6">
                {{ $productSettings['buy_button_text'] }}
            </button>
        </div>
    </div>

    {{-- Room for the bar so it never covers the last field. --}}
    <div class="h-20 md:hidden"></div>
@endif

@include('storefront.partials.product-scripts')

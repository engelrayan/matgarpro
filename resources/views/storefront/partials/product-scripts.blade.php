@push('scripts')
<script>
// Plain JS, no framework: this page is the ad landing page, and every kilobyte
// of script is paid for out of the merchant's ad budget.
(function () {
    const variants = @json($variantMap);
    const optionNames = @json(collect($product->options ?? [])->pluck('name')->values());
    const basePrice = {{ (float) $product->price }};
    const currency = @json($store->currency);
    const trackStock = {{ $product->track_stock ? 'true' : 'false' }};
    const buyLabel = @json($productSettings['buy_button_text']);
    const productStock = {{ (int) $product->stock }};

    const selected = {};
    const priceEl = document.getElementById('price');
    const totalEl = document.getElementById('total');
    const stickyTotalEl = document.getElementById('stickyTotal');
    const variantIdEl = document.getElementById('variantId');
    const qtyEl = document.getElementById('quantity');
    const submitEl = document.querySelector('#orderForm button[type="submit"]');

    const money = (n) => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Must match ProductVariant::keyFor() — same sort, same separators.
    const keyFor = (opts) =>
        Object.keys(opts).sort().map((k) => `${k}=${opts[k]}`).join('|');

    function currentVariant() {
        if (optionNames.length === 0) return null;
        if (optionNames.some((n) => !selected[n])) return undefined; // incomplete
        return variants[keyFor(selected)] ?? null;
    }

    function refresh() {
        const variant = currentVariant();
        const unit = variant ? variant.price : basePrice;
        const qty = Math.max(1, parseInt(qtyEl.value, 10) || 1);

        priceEl.textContent = money(unit);
        totalEl.textContent = `${money(unit * qty)} ${currency}`;
        if (stickyTotalEl) stickyTotalEl.textContent = money(unit * qty);
        variantIdEl.value = variant ? variant.id : '';

        // A combination the merchant never created is not buyable. Saying so
        // here beats letting them fill the whole form and fail on submit.
        const soldOut = variant === null && optionNames.length > 0
            ? true
            : variant
                ? trackStock && variant.stock < qty
                : trackStock && productStock < qty && optionNames.length === 0;

        submitEl.disabled = !!soldOut;
        // The merchant's own wording, not a second hardcoded copy of the
        // default — this line used to overwrite their button text on load.
        submitEl.textContent = soldOut ? 'غير متاح حاليًا' : buyLabel;
    }

    document.querySelectorAll('.opt').forEach((btn) => {
        btn.addEventListener('click', () => {
            const name = btn.dataset.option;
            selected[name] = btn.dataset.value;

            document.querySelectorAll(`.opt[data-option="${CSS.escape(name)}"]`).forEach((b) => {
                b.classList.toggle('border-primary', b === btn);
                b.classList.toggle('bg-primary/5', b === btn);
            });

            refresh();
        });
    });

    document.querySelectorAll('.qty').forEach((btn) => {
        btn.addEventListener('click', () => {
            const next = (parseInt(qtyEl.value, 10) || 1) + parseInt(btn.dataset.step, 10);
            qtyEl.value = Math.max(1, next);
            refresh();
        });
    });

    qtyEl.addEventListener('input', refresh);

    document.querySelectorAll('.thumb').forEach((thumb) => {
        thumb.addEventListener('click', () => {
            document.getElementById('mainImage').src = thumb.dataset.src;
            document.querySelectorAll('.thumb').forEach((t) => {
                t.classList.toggle('border-primary', t === thumb);
                t.classList.toggle('border-transparent', t !== thumb);
            });
        });
    });

    /*
     * Funnel beacon: fired once, the first time the customer actually touches
     * the order form. Not on page load — that is already the `view` event, and
     * counting every visitor as "started checkout" would make the conversion
     * rate meaningless.
     */
    let started = false;

    function markCheckoutStart() {
        if (started) return;
        started = true;

        const body = new FormData();
        body.append('product_id', '{{ $product->id }}');
        body.append('_token', '{{ csrf_token() }}');

        // keepalive so the beacon survives the customer navigating away mid-form.
        fetch('{{ route('storefront.checkout.start') }}', {
            method: 'POST',
            body,
            keepalive: true,
        }).catch(() => {});

        // Meta counts a filled form as intent, which is what it optimises
        // toward when there are not yet enough purchases to learn from.
        if (window.fbq) {
            fbq('track', 'InitiateCheckout', {
                content_ids: ['{{ $product->id }}'],
                content_type: 'product',
                currency: currency,
                value: parseFloat(totalEl.textContent) || basePrice,
            });
        }
    }

    document.getElementById('orderForm')
        .addEventListener('input', markCheckoutStart, { once: true });

    // Sticky bar: mirror the running total, and get out of the way once the
    // real button is visible so the customer is never shown two buy buttons.
    const stickyBar = document.getElementById('stickyBar');

    if (stickyBar && 'IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            stickyBar.classList.toggle('hidden', entry.isIntersecting);
        }).observe(submitEl);
    }

    // ViewContent: fired once on load, after the price is settled so the value
    // reported is the one the customer is actually looking at.
    if (window.fbq) {
        fbq('track', 'ViewContent', {
            content_ids: ['{{ $product->id }}'],
            content_name: @json($product->name),
            content_type: 'product',
            currency: currency,
            value: basePrice,
        });
    }

    refresh();
})();
</script>
@endpush

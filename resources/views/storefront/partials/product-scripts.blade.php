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

    // Delivery, when the store prices it by governorate. The price rides on
    // each <option>; the server charges the same figure from the same table.
    const freeShipping = {{ $productSettings['free_shipping'] ? 'true' : 'false' }};
    const governorateEl = document.getElementById('governorate');
    const shippingEl = document.getElementById('shippingAmount');

    function shippingFee() {
        if (!shippingEl || freeShipping) return 0;
        const option = governorateEl?.selectedOptions[0];
        return option?.dataset.shipping ? parseFloat(option.dataset.shipping) : 0;
    }

    function refreshShipping() {
        if (!shippingEl || freeShipping) return;
        const option = governorateEl?.selectedOptions[0];
        // The words live on the element, so the page carries one copy of them.
        shippingEl.textContent = option?.dataset.shipping
            ? (shippingFee() > 0 ? `${money(shippingFee())} ${currency}` : shippingEl.dataset.free)
            : shippingEl.dataset.choose;
    }

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

        const total = unit * qty + shippingFee();

        priceEl.textContent = money(unit);
        totalEl.textContent = `${money(total)} ${currency}`;
        if (stickyTotalEl) stickyTotalEl.textContent = money(total);
        refreshShipping();
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
                b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
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
    governorateEl?.addEventListener('change', refresh);

    /*
     * Gallery. The swiping is CSS scroll snapping; all this does is keep the
     * page dots and the desktop thumbnails in step with whichever photo is
     * actually on screen, and let a thumbnail scroll to its photo.
     */
    const gallery = document.querySelector('[data-gallery]');

    if (gallery && 'IntersectionObserver' in window) {
        const marks = document.querySelectorAll('[data-dot], [data-thumb]');

        const slideObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                const index = entry.target.dataset.slide;
                marks.forEach((m) => m.toggleAttribute('data-active', (m.dataset.dot ?? m.dataset.thumb) === index));
            });
        }, { root: gallery, threshold: 0.6 });

        gallery.querySelectorAll('[data-slide]').forEach((slide) => slideObserver.observe(slide));

        document.querySelectorAll('[data-thumb]').forEach((thumb) => {
            thumb.addEventListener('click', () => {
                const slide = gallery.querySelector(`[data-slide="${thumb.dataset.thumb}"]`);
                // Offsets rather than scrollIntoView, which would also scroll
                // the page; and negative in RTL, where the track runs leftward.
                const rtl = getComputedStyle(gallery).direction === 'rtl';
                const left = rtl
                    ? slide.offsetLeft + slide.offsetWidth - gallery.offsetWidth
                    : slide.offsetLeft;
                gallery.scrollTo({ left, behavior: 'smooth' });
            });
        });
    }

    /*
     * Funnel beacon: fired once, the first time the customer actually touches
     * the order form. Not on page load — that is already the `view` event, and
     * counting every visitor as "started checkout" would make the conversion
     * rate meaningless.
     */
    let started = false;

    /**
     * Send the beacon, carrying whatever has been typed so far.
     *
     * The same request does two jobs: it marks the funnel step, and it leaves
     * the merchant a recoverable draft. Splitting them would double the
     * requests on a page the merchant pays per visit for.
     */
    function sendBeacon() {
        const form = document.getElementById('orderForm');
        const body = new FormData();

        body.append('product_id', '{{ $product->id }}');
        body.append('_token', '{{ csrf_token() }}');
        body.append('variant_id', variantIdEl.value || '');
        body.append('quantity', qtyEl.value || '1');

        // Only the fields worth calling someone back about. The rest of the
        // form is not ours to keep from a customer who never submitted it.
        ['customer_name', 'customer_phone', 'governorate'].forEach((name) => {
            const field = form.elements[name];
            if (field && field.value) body.append(name, field.value);
        });

        // keepalive so the beacon survives the customer navigating away mid-form.
        fetch('{{ route('storefront.checkout.start') }}', {
            method: 'POST',
            body,
            keepalive: true,
        }).catch(() => {});
    }

    function markCheckoutStart() {
        if (started) return;
        started = true;

        sendBeacon();

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

    /*
     * Re-send once they stop typing, and again if they leave.
     *
     * The first beacon fires on the very first keystroke, when the phone field
     * is usually still empty — without these the draft would almost always be
     * unreachable, which is the whole value of keeping it.
     */
    let draftTimer;

    document.getElementById('orderForm').addEventListener('input', () => {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(sendBeacon, 2500);
    });

    // `pagehide`, not `unload`: mobile browsers frequently skip `unload`, and
    // a phone is where most of this traffic abandons.
    window.addEventListener('pagehide', () => {
        if (started) sendBeacon();
    });

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

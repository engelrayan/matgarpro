<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\StorefrontEvent;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Records the storefront funnel: view → checkout_start → order.
 *
 * Kept out of the controllers because tracking must never be the reason a page
 * fails. Every method here swallows its own errors: a customer looking at a
 * product does not care that our analytics write timed out.
 */
class FunnelTracker
{
    private const COOKIE = 'mv';

    private const COOKIE_DAYS = 365;

    /**
     * Same-visitor views of the same product inside this window count once.
     * A refresh or a back-button is not a new visit, and counting it as one
     * inflates traffic and deflates the conversion rate the merchant reads.
     */
    private const DEDUPE_MINUTES = 30;

    public function view(Request $request, Store $store, Product $product): void
    {
        $this->record($request, $store, StorefrontEvent::TYPE_VIEW, $product, dedupe: true);
    }

    public function checkoutStart(Request $request, Store $store, ?Product $product = null): void
    {
        $this->record($request, $store, StorefrontEvent::TYPE_CHECKOUT_START, $product, dedupe: true);
    }

    /** Orders are never de-duplicated — two orders are two orders. */
    public function order(Request $request, Store $store, ?Product $product = null): void
    {
        $this->record($request, $store, StorefrontEvent::TYPE_ORDER, $product, dedupe: false);
    }

    /**
     * The visitor's anonymous id, minting and queuing the cookie if this is
     * their first page. Read from the queued value too, so several events in
     * one request share a single id instead of creating a visitor each time.
     */
    public function visitorId(Request $request): string
    {
        if ($existing = $request->cookie(self::COOKIE)) {
            return (string) $existing;
        }

        if ($queued = Cookie::queued(self::COOKIE)) {
            return $queued->getValue();
        }

        $id = (string) Str::uuid();

        // Plain cookie: it holds no secret, and encrypting it would change the
        // value on every rotation of the app key, splitting one visitor in two.
        Cookie::queue(Cookie::make(
            name: self::COOKIE,
            value: $id,
            minutes: self::COOKIE_DAYS * 24 * 60,
            httpOnly: true,
            sameSite: 'lax',
        ));

        return $id;
    }

    private function record(
        Request $request,
        Store $store,
        string $type,
        ?Product $product,
        bool $dedupe,
    ): void {
        try {
            $visitorId = $this->visitorId($request);

            if ($dedupe && $this->seenRecently($store, $type, $product, $visitorId)) {
                return;
            }

            StorefrontEvent::create([
                'store_id' => $store->id,
                'product_id' => $product?->id,
                'type' => $type,
                'visitor_id' => $visitorId,
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'referrer' => Str::limit((string) $request->headers->get('referer'), 500, ''),
            ]);
        } catch (\Throwable) {
            // Analytics is never worth a 500 on a page a customer is buying from.
        }
    }

    private function seenRecently(Store $store, string $type, ?Product $product, string $visitorId): bool
    {
        return StorefrontEvent::where('store_id', $store->id)
            ->where('type', $type)
            ->where('visitor_id', $visitorId)
            ->when($product, fn ($q) => $q->where('product_id', $product->id))
            ->where('created_at', '>=', now()->subMinutes(self::DEDUPE_MINUTES))
            ->exists();
    }
}

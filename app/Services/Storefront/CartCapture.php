<?php

namespace App\Services\Storefront;

use App\Models\AbandonedCart;
use App\Models\Order;
use App\Models\Store;
use App\Support\ArabicNumerals;
use Illuminate\Http\Request;

/**
 * Remembers a half-filled order form, and clears it when the order arrives.
 *
 * Everything here is best-effort: a customer typing into a form must never see
 * an error because we failed to save a draft of it.
 */
class CartCapture
{
    public function __construct(private readonly FunnelTracker $funnel) {}

    /**
     * Save what the customer has typed so far.
     *
     * One row per visitor per product, updated in place: someone who corrects
     * their number four times leaves one cart, not four.
     *
     * @param  array<string,mixed>  $input
     */
    public function remember(Request $request, Store $store, array $input): void
    {
        // A showroom's visitors are us. Nothing to recover, and nothing that
        // should reach a merchant's follow-up list.
        if ($store->is_demo) {
            return;
        }

        $phone = ArabicNumerals::digitsOnly((string) ($input['customer_phone'] ?? ''));

        // Before a phone there is nothing to chase; the funnel already counts
        // the fact that they started.
        if (strlen($phone) < 8) {
            return;
        }

        try {
            $productId = (int) ($input['product_id'] ?? 0) ?: null;

            AbandonedCart::updateOrCreate(
                [
                    'store_id' => $store->id,
                    'visitor_id' => $this->funnel->visitorId($request),
                    'product_id' => $productId,
                ],
                [
                    'product_variant_id' => (int) ($input['variant_id'] ?? 0) ?: null,
                    'quantity' => max(1, (int) ($input['quantity'] ?? 1)),
                    'customer_name' => $this->clean($input['customer_name'] ?? null, 255),
                    'customer_phone' => $phone,
                    'governorate' => $this->clean($input['governorate'] ?? null, 255),
                ],
            );
        } catch (\Throwable) {
            // A draft nobody asked for is not worth a failed request.
        }
    }

    /**
     * Close the cart this order came from.
     *
     * Matched on the visitor first and the phone second: the same person often
     * finishes on a different device than the one they started on, and a cart
     * that stays open after the sale sends the customer a message about an
     * order they already placed.
     */
    public function recover(Request $request, Store $store, Order $order): void
    {
        try {
            $visitorId = $this->funnel->visitorId($request);

            $store->abandonedCarts()
                ->whereNull('recovered_at')
                ->where(fn ($q) => $q
                    ->where('visitor_id', $visitorId)
                    ->orWhere('customer_phone', $order->customer_phone))
                ->update([
                    'recovered_order_id' => $order->id,
                    'recovered_at' => now(),
                ]);
        } catch (\Throwable) {
            // The order is the thing that matters; the cart is bookkeeping.
        }
    }

    private function clean(?string $value, int $limit): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}

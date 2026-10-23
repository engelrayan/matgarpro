<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Gives stock back when an order stops needing it, and takes it again if the
 * order comes back to life.
 *
 * Checkout draws stock down the moment an order is placed. Without this, every
 * cancelled or returned order kept its units out of the shop for good, and a
 * product with a 30% return rate showed "sold out" while the boxes sat on the
 * merchant's shelf.
 */
class StockKeeper
{
    /** Statuses whose units are no longer spoken for. */
    private const RELEASED = [Order::STATUS_CANCELLED, Order::STATUS_RETURNED];

    public function statusChanged(Order $order, string $from, string $to): void
    {
        $wasHeld = ! in_array($from, self::RELEASED, true);
        $isHeld = ! in_array($to, self::RELEASED, true);

        // Cancelled → returned, or confirmed → shipped: the units did not move.
        if ($wasHeld === $isHeld) {
            return;
        }

        foreach ($order->items()->get() as $item) {
            $this->adjust($item, $isHeld ? -$item->quantity : $item->quantity);
        }
    }

    /** Mirrors PlaceOrder::decrementStock — the variant holds the count when there is one. */
    private function adjust(OrderItem $item, int $delta): void
    {
        // Trashed too: a product deleted after the sale still has the units.
        $product = Product::withTrashed()->find($item->product_id);

        if (! $product?->track_stock || $delta === 0) {
            return;
        }

        $target = $item->product_variant_id
            ? ProductVariant::where('product_id', $product->id)->whereKey($item->product_variant_id)->first()
            : $product;

        if (! $target) {
            return;
        }

        $delta > 0 ? $target->increment('stock', $delta) : $target->decrement('stock', -$delta);
    }
}

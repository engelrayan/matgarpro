<?php

namespace App\Actions\Orders;

use App\Exceptions\CheckoutException;
use App\Jobs\SendMetaPurchaseEvent;
use App\Jobs\SendWhatsappOrderConfirmation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\Billing\UsageBiller;
use App\Services\Shipping\ShippingRates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns a filled-in storefront form into an order.
 *
 * Everything that decides whether the sale is real — stock, prices, totals — is
 * read from the database inside one locked transaction. Nothing the browser
 * sent is trusted beyond "which product" and "how many": a posted price is an
 * offer from an attacker, not a fact.
 */
class PlaceOrder
{
    public function __construct(private readonly UsageBiller $biller) {}

    /**
     * @param  array<int,array{product_id:int,variant_id?:int|null,quantity:int,offer_id?:int|null}>  $lines
     * @param  array<string,mixed>  $customer
     *
     * @throws CheckoutException
     */
    public function handle(Store $store, array $lines, array $customer): Order
    {
        if ($store->is_demo) {
            throw new CheckoutException('ده معرض للثيم مش متجر حقيقي — الطلب مش هيتسجّل. اعمل متجرك إنت وابدأ بيع.');
        }

        if (! $store->canAcceptOrders()) {
            throw new CheckoutException('المتجر ده مش بيستقبل طلبات دلوقتي.');
        }

        if ($lines === []) {
            throw new CheckoutException('مفيش منتجات في الطلب.');
        }

        $order = DB::transaction(function () use ($store, $lines, $customer) {
            /*
             | The store row first, before any product. It serialises this
             | shop's checkouts for the few milliseconds the transaction lasts,
             | which is what makes `nextNumber()` safe: product locks alone let
             | two orders for *different* products read the same max, and the
             | loser hit the unique index as a 500 — a lost sale, most likely
             | in the minute an ad starts converting. Always store-then-product,
             | so two checkouts can never wait on each other in a circle.
             */
            $locked = Store::whereKey($store->id)->lockForUpdate()->first() ?? $store;

            $items = $this->resolveLines($store, $lines);

            $subtotal = array_sum(array_column($items, 'total'));
            $shipping = $this->shipping($locked, $customer['governorate'] ?? null, $items);

            /*
             | Past the credit floor the order is still taken — stock, number,
             | the customer's "we got your order" — but it arrives hidden and
             | unbilled, and opens when a top-up covers it. Read from the
             | locked row, so two checkouts racing past the floor agree.
             */
            $hidden = $locked->hidesNewOrders();

            $order = $store->orders()->create([
                'number' => $this->nextNumber($store),
                'customer_name' => $customer['customer_name'],
                'customer_phone' => $customer['customer_phone'],
                'customer_phone_alt' => $customer['customer_phone_alt'] ?? null,
                'customer_email' => $customer['customer_email'] ?? null,
                'governorate' => $customer['governorate'] ?? null,
                'city' => $customer['city'] ?? null,
                'address' => $customer['address'] ?? null,
                'note' => $customer['note'] ?? null,
                'subtotal' => $subtotal,
                // Written onto the order, never looked up again: a merchant
                // who raises their prices tomorrow must not change what an
                // order taken today asks the courier to collect.
                'shipping_amount' => $shipping,
                'discount_amount' => 0,
                'total' => $subtotal + $shipping,
                'status' => Order::STATUS_PENDING,
                'payment_method' => 'cod',
                'ip' => $customer['ip'] ?? null,
                'user_agent' => $customer['user_agent'] ?? null,
                'tracking' => $customer['tracking'] ?? null,
                'locked_at' => $hidden ? now() : null,
            ]);

            foreach ($items as $item) {
                $order->items()->create($item['attributes']);
                $this->decrementStock($item);
            }

            return $order;
        });

        // Billing sits outside the order transaction on purpose. A store that
        // is somehow un-billable must still keep the order it just took — the
        // merchant's sale is worth more than our fee, and the usage row can be
        // reconciled later. A hidden order is billed when it opens, not now.
        if (! $order->isLocked()) {
            $this->biller->chargeForOrder($store, $order, "طلب رقم {$order->number}");
        }

        // The ad platforms hear about every sale, hidden or not: it reveals
        // nothing to the merchant, and starving the pixel would cost them the
        // ad optimisation they are paying for.
        $this->reportConversion($store, $order);

        // Hidden or not: an empty balance hides the customer from the
        // merchant, it never stops the customer being asked. A confirmation
        // held back until a top-up is a customer who has stopped waiting —
        // and a cash-on-delivery order nobody confirmed is a parcel returned.
        $this->askCustomerToConfirm($store, $order);

        return $order;
    }

    /**
     * Ask the customer to confirm the order on WhatsApp.
     *
     * Queued rather than sent here, and wrapped: cash-on-delivery lives or dies
     * on confirmation rates, but a gateway having a bad minute must never cost
     * the merchant a sale that has already been taken.
     */
    private function askCustomerToConfirm(Store $store, Order $order): void
    {
        try {
            $link = $store->whatsappIntegration;

            if ($link?->canSend() && $link->auto_send) {
                SendWhatsappOrderConfirmation::dispatch($order->id);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to queue WhatsApp confirmation', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Queue a server-side Purchase event for each of the store's pixels.
     *
     * One job per pixel: a store may run several, and a revoked token on one
     * must not stop the others reporting. Queued rather than inline so a slow
     * Graph API can never show up as a slow thank-you page — and wrapped,
     * because a tracking failure must never lose the merchant a sale that has
     * already been taken and paid for.
     */
    private function reportConversion(Store $store, Order $order): void
    {
        try {
            foreach ($store->pixels()->where('is_active', true)->get() as $pixel) {
                if ($pixel->canSendServerSide()) {
                    SendMetaPurchaseEvent::dispatch($order->id, $pixel->id);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to queue conversion events', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Lock every product and variant, then price the lines from the database.
     *
     * @param  array<int,array<string,mixed>>  $lines
     * @return array<int,array<string,mixed>>
     *
     * @throws CheckoutException
     */
    private function resolveLines(Store $store, array $lines): array
    {
        $items = [];

        // Pieces in each offer actually ordered, product id => offer id => count.
        $offerPieces = [];

        foreach ($this->mergeLines($lines) as $line) {
            /** @var Product|null $product */
            $product = $store->products()
                ->whereKey($line['product_id'])
                ->where('status', Product::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $product) {
                throw new CheckoutException('في منتج مش متاح دلوقتي.');
            }

            $quantity = max(1, (int) $line['quantity']);
            $variant = null;

            if ($product->hasVariants()) {
                /** @var ProductVariant|null $variant */
                $variant = $product->variants()
                    ->whereKey($line['variant_id'] ?? null)
                    ->lockForUpdate()
                    ->first();

                if (! $variant) {
                    throw new CheckoutException("اختار المواصفات لمنتج: {$product->name}");
                }
            }

            if (! $product->canFulfil($quantity, $variant)) {
                throw new CheckoutException("الكمية المطلوبة مش متوفرة من: {$product->name}");
            }

            $offer = $this->offerFor($product, $line['offer_id'] ?? null);

            if ($offer) {
                $offerPieces[$product->id][$offer->id] = ($offerPieces[$product->id][$offer->id] ?? 0) + $quantity;
            }

            // An offer's price holds whatever size or colour each piece is.
            $unitPrice = $offer
                ? (float) $offer->unit_price
                : (float) ($variant?->effectivePrice($product) ?? $product->price);

            $items[] = [
                'product' => $product,
                'variant' => $variant,
                'offer' => $offer,
                'quantity' => $quantity,
                'total' => $unitPrice * $quantity,
                'attributes' => [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'name' => $product->name,
                    'variant_label' => $variant?->label(),
                    'offer_label' => $offer?->label(),
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'total' => $unitPrice * $quantity,
                ],
            ];
        }

        /*
         | An offer is a price for an exact number of pieces. Posting the offer
         | with a different count — one piece at the three-piece price — is the
         | first thing anyone tampering with this form would try.
         */
        foreach ($items as $item) {
            if ($offer = $item['offer']) {
                if ($offerPieces[$item['product']->id][$offer->id] !== $offer->quantity) {
                    throw new CheckoutException('عدد القطع مش مطابق للعرض. حدّث الصفحة واختار العرض تاني.');
                }
            }
        }

        return $items;
    }

    /**
     * The offer this line is bought under, read from the locked product.
     *
     * A product with offers is sold only through them. One without any
     * ignores a posted offer id rather than failing on it — the merchant may
     * have removed every offer while the page sat open.
     *
     * @throws CheckoutException
     */
    private function offerFor(Product $product, mixed $offerId): ?ProductOffer
    {
        $offers = $product->offers()->get();

        if ($offers->isEmpty()) {
            return null;
        }

        $offer = $offers->firstWhere('id', (int) $offerId);

        if (! $offer) {
            throw new CheckoutException(filled($offerId)
                ? 'العرض ده اتغيّر. حدّث الصفحة واختار العرض تاني.'
                : "اختار عرض من عروض: {$product->name}");
        }

        return $offer;
    }

    /**
     * One line per product, variant and offer.
     *
     * An offer with sizes arrives a piece at a time, and two pieces of the
     * same size must be checked against stock together — two lines of one
     * each would each see the last piece on the shelf and both pass.
     *
     * @param  array<int,array<string,mixed>>  $lines
     * @return array<int,array<string,mixed>>
     */
    private function mergeLines(array $lines): array
    {
        $merged = [];

        foreach ($lines as $line) {
            $key = implode(':', [
                (int) $line['product_id'],
                (int) ($line['variant_id'] ?? 0),
                (int) ($line['offer_id'] ?? 0),
            ]);

            if (isset($merged[$key])) {
                $merged[$key]['quantity'] = (int) $merged[$key]['quantity'] + max(1, (int) $line['quantity']);
            } else {
                $merged[$key] = $line;
            }
        }

        return array_values($merged);
    }

    /**
     * The delivery charge, from the store's own prices.
     *
     * Read from the locked store row, so a merchant saving new prices while
     * this order is being written cannot leave it half on the old ones. The
     * checkout already refused governorates the store does not deliver to;
     * this refuses them again for any caller that skipped it.
     *
     * @param  array<int,array<string,mixed>>  $items
     *
     * @throws CheckoutException
     */
    private function shipping(Store $store, ?string $governorate, array $items): float
    {
        $fee = ShippingRates::for($store)->feeFor($governorate, array_column($items, 'product'));

        if ($fee === null) {
            throw new CheckoutException(filled($governorate)
                ? 'للأسف مش بنوصّل للمحافظة دي.'
                : 'اختار المحافظة عشان نحسب الشحن.');
        }

        // An offer that promises free delivery keeps the promise — but only
        // after the governorate check above: it is free shipping, not
        // shipping to places the store does not go.
        $allFree = collect($items)->every(fn (array $item) => $item['offer']?->free_shipping);

        return $allFree ? 0.0 : $fee;
    }

    /** @param array<string,mixed> $item */
    private function decrementStock(array $item): void
    {
        /** @var Product $product */
        $product = $item['product'];

        if (! $product->track_stock) {
            return;
        }

        if ($variant = $item['variant']) {
            $variant->decrement('stock', $item['quantity']);

            return;
        }

        $product->decrement('stock', $item['quantity']);
    }

    /**
     * Next per-store order number.
     *
     * Only safe inside the caller's transaction, after it has locked the store
     * row; the unique index on (store_id, number) stays as the backstop.
     */
    private function nextNumber(Store $store): int
    {
        return (int) $store->orders()->withTrashed()->max('number') + 1;
    }
}

<?php

namespace App\Actions\Orders;

use App\Exceptions\CheckoutException;
use App\Jobs\SendMetaPurchaseEvent;
use App\Jobs\SendWhatsappOrderConfirmation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\Billing\UsageBiller;
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
     * @param  array<int,array{product_id:int,variant_id?:int|null,quantity:int}>  $lines
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
            $items = $this->resolveLines($store, $lines);

            $subtotal = array_sum(array_column($items, 'total'));

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
                'shipping_amount' => 0,
                'discount_amount' => 0,
                'total' => $subtotal,
                'status' => Order::STATUS_PENDING,
                'payment_method' => 'cod',
                'ip' => $customer['ip'] ?? null,
                'user_agent' => $customer['user_agent'] ?? null,
                'tracking' => $customer['tracking'] ?? null,
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
        // reconciled later.
        $this->biller->chargeForOrder($store, $order, "طلب رقم {$order->number}");

        $this->reportConversion($store, $order);
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

        foreach ($lines as $line) {
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

            $unitPrice = (float) ($variant?->effectivePrice($product) ?? $product->price);

            $items[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => $quantity,
                'total' => $unitPrice * $quantity,
                'attributes' => [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'name' => $product->name,
                    'variant_label' => $variant?->label(),
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'total' => $unitPrice * $quantity,
                ],
            ];
        }

        return $items;
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
     * Read inside the caller's transaction while the store's other rows are
     * already locked; the unique index on (store_id, number) is the real
     * guarantee if two checkouts ever land in the same millisecond.
     */
    private function nextNumber(Store $store): int
    {
        return (int) $store->orders()->withTrashed()->max('number') + 1;
    }
}

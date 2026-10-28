<?php

namespace App\Services\Billing;

use App\Jobs\SendWhatsappOrderConfirmation;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Opens a store's hidden orders once its balance can carry them.
 *
 * Oldest first — the customer who has waited longest is the one most likely
 * to have given up — and one at a time, each charged as it opens, for as long
 * as the store stays above its credit floor: the same test that hid them
 * (Store::hidesNewOrders), so an order is never opened into a balance that
 * would have hidden it.
 *
 * A hidden order is asked to confirm when it is placed, like any other. One
 * that never was — hidden back when the balance held confirmations too — gets
 * it as it opens, if the store sends them automatically.
 *
 * Run after anything that adds money: a confirmed top-up, an operator's
 * manual credit, a change of price that makes orders free.
 */
class OrderUnlocker
{
    public function __construct(private readonly UsageBiller $biller) {}

    /** @return int how many orders were opened */
    public function unlock(Store $store): int
    {
        $opened = 0;

        while (! $store->refresh()->hidesNewOrders()) {
            $order = $this->openOldest($store);

            if ($order === null) {
                break;
            }

            // Idempotent: a retried unlock never charges an order twice.
            $this->biller->chargeForOrder($store, $order, "طلب رقم {$order->number}");
            $this->askCustomerToConfirm($store, $order);

            $opened++;
        }

        return $opened;
    }

    /**
     * Take the oldest hidden order and open it, under a row lock, so two
     * top-ups confirmed at once cannot both open — and charge — the same one.
     */
    private function openOldest(Store $store): ?Order
    {
        return DB::transaction(function () use ($store) {
            $order = $store->orders()->locked()->orderBy('id')->lockForUpdate()->first();

            $order?->forceFill(['locked_at' => null])->save();

            return $order;
        });
    }

    private function askCustomerToConfirm(Store $store, Order $order): void
    {
        try {
            $link = $store->whatsappIntegration;

            if ($link?->canSend() && $link->auto_send && $order->whatsapp_state === null) {
                SendWhatsappOrderConfirmation::dispatch($order->id);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to queue WhatsApp confirmation for an unlocked order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

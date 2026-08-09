<?php

namespace App\Services\Billing;

use App\Models\Store;
use App\Models\StoreUsageEvent;
use App\Models\StoreWalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Charges a store for something it used.
 *
 * Two rules hold this together, and both exist because the alternative has
 * already cost real money elsewhere:
 *
 *  1. The price is captured on the event row at the moment it happens. Nothing
 *     downstream recomputes it, so re-pricing a store tomorrow cannot rewrite
 *     what it was billed yesterday.
 *  2. Charging is idempotent at the database level. A retried job, a replayed
 *     webhook or a double-submitted checkout hits a unique index, not a second
 *     deduction.
 */
class UsageBiller
{
    public function __construct(private readonly WalletService $wallet) {}

    /**
     * Bill a store for one order. Returns the usage row — the existing one if
     * this order was already billed, so callers never need to check first.
     */
    public function chargeForOrder(Store $store, Model $order, ?string $description = null): StoreUsageEvent
    {
        return $this->charge($store, StoreUsageEvent::TYPE_ORDER, $order, $description ?? 'رسوم طلب');
    }

    public function charge(Store $store, string $type, Model $billable, string $description): StoreUsageEvent
    {
        $existing = $this->findExisting($store, $type, $billable);

        if ($existing) {
            return $existing;
        }

        $unitPrice = $store->pricePerOrder();

        try {
            return DB::transaction(function () use ($store, $type, $billable, $description, $unitPrice) {
                $transaction = null;

                // A zero price still records the event. Usage history has to be
                // complete for free stores too, otherwise moving a store onto a
                // paid plan leaves an unexplained hole in its numbers.
                if ($unitPrice > 0) {
                    $transaction = $this->wallet->debit(
                        store: $store,
                        amount: $unitPrice,
                        type: StoreWalletTransaction::TYPE_ORDER_FEE,
                        description: $description,
                        source: $billable,
                    );
                }

                return StoreUsageEvent::create([
                    'store_id' => $store->id,
                    'type' => $type,
                    'billable_type' => $billable->getMorphClass(),
                    'billable_id' => $billable->getKey(),
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                    'amount' => $unitPrice,
                    'billing_plan_id' => $store->billing_plan_id,
                    'price_source' => $store->priceSource(),
                    'wallet_transaction_id' => $transaction?->id,
                    'occurred_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            // Lost a race against a concurrent identical charge. The unique
            // index did its job; return the row the winner wrote.
            $existing = $this->findExisting($store, $type, $billable);

            if ($existing) {
                return $existing;
            }

            throw $e;
        }
    }

    private function findExisting(Store $store, string $type, Model $billable): ?StoreUsageEvent
    {
        return StoreUsageEvent::where('store_id', $store->id)
            ->where('type', $type)
            ->where('billable_type', $billable->getMorphClass())
            ->where('billable_id', $billable->getKey())
            ->first();
    }
}

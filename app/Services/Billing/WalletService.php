<?php

namespace App\Services\Billing;

use App\Models\Store;
use App\Models\StoreWalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only place a store's balance is allowed to move.
 *
 * Every change writes a ledger row and the cached balance together, inside one
 * transaction, holding a row lock on the store. Nothing else in the codebase
 * may touch `stores.balance` — that is why it is not fillable on the model.
 */
class WalletService
{
    /** Merchant added credit. */
    public function credit(
        Store $store,
        float $amount,
        string $type = StoreWalletTransaction::TYPE_TOPUP,
        ?string $description = null,
        ?Model $source = null,
        ?int $createdBy = null,
        array $meta = [],
    ): StoreWalletTransaction {
        return $this->apply($store, abs($amount), $type, $description, $source, $createdBy, $meta);
    }

    /** We took money for something the store used. */
    public function debit(
        Store $store,
        float $amount,
        string $type = StoreWalletTransaction::TYPE_ORDER_FEE,
        ?string $description = null,
        ?Model $source = null,
        ?int $createdBy = null,
        array $meta = [],
    ): StoreWalletTransaction {
        return $this->apply($store, -1 * abs($amount), $type, $description, $source, $createdBy, $meta);
    }

    /**
     * Sum of the ledger, for reconciling against the cached column. If these
     * two ever disagree, the ledger wins — it is the record of what happened.
     */
    public function ledgerBalance(Store $store): float
    {
        return (float) StoreWalletTransaction::where('store_id', $store->id)->sum('amount');
    }

    private function apply(
        Store $store,
        float $signedAmount,
        string $type,
        ?string $description,
        ?Model $source,
        ?int $createdBy,
        array $meta,
    ): StoreWalletTransaction {
        return DB::transaction(function () use ($store, $signedAmount, $type, $description, $source, $createdBy, $meta) {
            // Lock the row first: two concurrent orders must not both read the
            // same starting balance and write conflicting `balance_after`.
            $locked = Store::whereKey($store->getKey())->lockForUpdate()->firstOrFail();

            $balanceAfter = round((float) $locked->balance + $signedAmount, 2);

            $transaction = StoreWalletTransaction::create([
                'store_id' => $locked->id,
                'type' => $type,
                'amount' => round($signedAmount, 2),
                'balance_after' => $balanceAfter,
                'description' => $description,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'created_by' => $createdBy,
                'meta' => $meta ?: null,
            ]);

            $locked->forceFill(['balance' => $balanceAfter])->save();

            // Keep the caller's instance in step with what we just wrote.
            $store->setAttribute('balance', $balanceAfter);

            return $transaction;
        });
    }
}

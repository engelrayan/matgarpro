<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Customers\ReputationService;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the platform's delivery record in step with order outcomes.
 *
 * An observer rather than a call in the controller: an order's status is
 * changed from the grid, the order page, the bulk action and Daman's webhook,
 * and a record that four call sites have to remember to update is a record
 * that is wrong by next month.
 */
class OrderObserver
{
    public function __construct(private readonly ReputationService $reputation) {}

    public function saved(Order $order): void
    {
        // Only when something that matters actually moved. Editing an address
        // must not rewrite the customer's history.
        if (! $order->wasChanged('status') && ! $order->wasRecentlyCreated) {
            return;
        }

        try {
            $this->reputation->record($order);
        } catch (\Throwable $e) {
            /*
             | Never let this break the thing that triggered it.
             |
             | A merchant marking twenty orders delivered must not see a failure
             | because an aggregate could not be written — the order status is
             | the fact, and the record can be rebuilt from entries at any time.
             */
            Log::warning('Failed to record customer reputation', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

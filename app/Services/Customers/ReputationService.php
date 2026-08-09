<?php

namespace App\Services\Customers;

use App\Models\CustomerReputation;
use App\Models\CustomerReputationEntry;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Keeps each phone number's delivery record current.
 *
 * The aggregate is always recomputed from its entries rather than incremented
 * in place. Merchants correct order statuses constantly — marked delivered,
 * then the courier reports a return — and a counter that only ever goes up
 * turns one parcel into two data points and eventually accuses a good customer.
 */
class ReputationService
{
    /** Maps an order status onto the only three outcomes that matter here. */
    private const OUTCOMES = [
        Order::STATUS_DELIVERED => CustomerReputationEntry::OUTCOME_DELIVERED,
        Order::STATUS_CANCELLED => CustomerReputationEntry::OUTCOME_REFUSED,
        Order::STATUS_RETURNED => CustomerReputationEntry::OUTCOME_REFUSED,
        Order::STATUS_SHIPPED => CustomerReputationEntry::OUTCOME_PENDING,
    ];

    /**
     * Record what happened to an order.
     *
     * Statuses before dispatch contribute nothing: an order sitting in review
     * says nothing about the customer, and counting a cancellation the merchant
     * made themselves would blame the buyer for the shop's own decision.
     */
    public function record(Order $order): void
    {
        $outcome = self::OUTCOMES[$order->status] ?? null;
        $phone = trim((string) $order->customer_phone);

        if ($phone === '') {
            return;
        }

        DB::transaction(function () use ($order, $phone, $outcome) {
            $reputation = CustomerReputation::firstOrCreate(
                ['phone' => $phone],
                ['first_seen_at' => now()],
            );

            if ($outcome === null) {
                // Rolled back to a pre-dispatch status — drop any contribution
                // this order had already made.
                $reputation->entries()->where('order_id', $order->id)->delete();
            } else {
                $reputation->entries()->updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'store_id' => $order->store_id,
                        'outcome' => $outcome,
                        'settled_at' => $outcome === CustomerReputationEntry::OUTCOME_PENDING ? null : now(),
                    ],
                );
            }

            $this->recount($reputation);
        });
    }

    /** Rebuild the aggregate from the entries that still count. */
    public function recount(CustomerReputation $reputation): void
    {
        $cutoff = now()->subDays((int) config('reputation.window_days'));

        $entries = $reputation->entries()
            // A refusal from two years ago says nothing about today.
            ->where('created_at', '>=', $cutoff)
            ->get();

        $reputation->forceFill([
            'delivered' => $entries->where('outcome', CustomerReputationEntry::OUTCOME_DELIVERED)->count(),
            'refused' => $entries->where('outcome', CustomerReputationEntry::OUTCOME_REFUSED)->count(),
            'pending' => $entries->where('outcome', CustomerReputationEntry::OUTCOME_PENDING)->count(),
            // Distinct stores, so one shop's repeated refusals read as one
            // shop's experience rather than a platform-wide pattern.
            'stores_count' => $entries->pluck('store_id')->unique()->count(),
            'last_outcome_at' => $entries->max('settled_at') ?: $reputation->last_outcome_at,
        ])->save();
    }

    /**
     * The record for a phone, or null when we have never shipped to it.
     *
     * Null rather than an empty row: "we know nothing about this number" and
     * "this number has a clean record" are different things, and only one of
     * them is worth putting on screen.
     */
    public function for(?string $phone): ?CustomerReputation
    {
        $phone = trim((string) $phone);

        return $phone === '' ? null : CustomerReputation::where('phone', $phone)->first();
    }
}

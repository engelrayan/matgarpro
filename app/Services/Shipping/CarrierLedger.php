<?php

namespace App\Services\Shipping;

use App\Models\CarrierCollection;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Where a store's shipments are, and where its money is.
 *
 * Every order is cash on delivery, so between the door and the merchant's
 * pocket the money sits with a carrier. This answers, per carrier: how many
 * parcels are on the road and what they are worth, how many were delivered
 * and how much of that money the carrier still holds, what has been paid
 * over, and how many came back.
 *
 * The carrier is the one Daman named on the order. Orders shipped without
 * Daman have none and are grouped together under MANUAL — the merchant's own
 * courier still has to hand the money over, and still gets recorded here.
 */
class CarrierLedger
{
    /** The key for orders that went out without Daman naming a carrier. */
    public const MANUAL = '__manual';

    private const SHIPPED_STATUSES = [Order::STATUS_SHIPPED, Order::STATUS_DELIVERED, Order::STATUS_RETURNED];

    /**
     * One row per carrier the store has shipped with, busiest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function carriers(Store $store): array
    {
        $shipped = Order::STATUS_SHIPPED;
        $delivered = Order::STATUS_DELIVERED;
        $returned = Order::STATUS_RETURNED;

        // One pass over the store's shipped orders, every figure a
        // conditional sum, rather than a query per carrier per figure.
        $rows = $store->orders()
            ->whereIn('status', self::SHIPPED_STATUSES)
            ->groupBy('daman_carrier_name')
            ->selectRaw('daman_carrier_name as carrier')
            ->selectRaw("SUM(status = ?) as in_transit_count", [$shipped])
            ->selectRaw("COALESCE(SUM(CASE WHEN status = ? THEN total END), 0) as in_transit_value", [$shipped])
            ->selectRaw("SUM(status = ?) as delivered_count", [$delivered])
            ->selectRaw("SUM(status = ? AND carrier_collection_id IS NULL) as due_count", [$delivered])
            ->selectRaw("COALESCE(SUM(CASE WHEN status = ? AND carrier_collection_id IS NULL THEN total END), 0) as due_value", [$delivered])
            ->selectRaw("SUM(status = ?) as returned_count", [$returned])
            ->get()
            ->keyBy(fn ($row) => $row->carrier ?? self::MANUAL);

        $collected = CarrierCollection::query()
            ->where('store_id', $store->id)
            ->groupBy('carrier')
            ->selectRaw('carrier, SUM(amount) as collected_value, COUNT(*) as collections, MAX(received_on) as last_collected_on')
            ->get()
            ->keyBy(fn ($row) => $row->carrier ?? self::MANUAL);

        return $rows->keys()->merge($collected->keys())->unique()
            ->map(function (string $key) use ($rows, $collected) {
                $row = $rows->get($key);
                $paid = $collected->get($key);
                $delivered = (int) ($row->delivered_count ?? 0);
                $returned = (int) ($row->returned_count ?? 0);

                return [
                    'key' => $key,
                    'name' => $key === self::MANUAL ? null : $key,
                    'in_transit_count' => (int) ($row->in_transit_count ?? 0),
                    'in_transit_value' => round((float) ($row->in_transit_value ?? 0), 2),
                    'delivered_count' => $delivered,
                    'due_count' => (int) ($row->due_count ?? 0),
                    'due_value' => round((float) ($row->due_value ?? 0), 2),
                    'returned_count' => $returned,
                    'collected_value' => round((float) ($paid->collected_value ?? 0), 2),
                    'last_collected_on' => $paid?->last_collected_on,
                    // Of the parcels that reached an end, how many were
                    // accepted; null until one has.
                    'delivery_rate' => $delivered + $returned > 0 ? (int) round($delivered / ($delivered + $returned) * 100) : null,
                ];
            })
            ->sortByDesc(fn (array $c) => $c['due_value'] + $c['in_transit_value'])
            ->values()
            ->all();
    }

    /**
     * The store-wide figures, summed from the carrier rows.
     *
     * @param  array<int,array<string,mixed>>  $carriers
     * @return array<string,float|int>
     */
    public function totals(array $carriers): array
    {
        $sum = fn (string $field) => collect($carriers)->sum($field);

        return [
            'in_transit_count' => (int) $sum('in_transit_count'),
            'in_transit_value' => round((float) $sum('in_transit_value'), 2),
            'due_count' => (int) $sum('due_count'),
            'due_value' => round((float) $sum('due_value'), 2),
            'collected_value' => round((float) $sum('collected_value'), 2),
            'returned_count' => (int) $sum('returned_count'),
        ];
    }

    /**
     * Delivered orders a carrier has not paid for yet: what a collection
     * offers to settle, oldest first.
     *
     * @return Collection<int,Order>
     */
    public function due(Store $store, string $carrier): Collection
    {
        return $this->forCarrier($store->orders(), $carrier)
            ->where('status', Order::STATUS_DELIVERED)
            ->whereNull('carrier_collection_id')
            ->orderBy('id')
            ->get(['id', 'number', 'customer_name', 'governorate', 'total', 'daman_tracking_number', 'locked_at', 'updated_at']);
    }

    /**
     * Record a carrier paying for these orders.
     *
     * Only orders that are this carrier's, delivered and not yet paid for may
     * be settled — checked again here, inside the transaction, so a second
     * tab cannot settle the same order twice.
     *
     * @param  array<int,int>  $orderIds
     */
    public function record(
        Store $store,
        ?User $user,
        string $carrier,
        array $orderIds,
        float $amount,
        string $receivedOn,
        ?string $reference = null,
        ?string $note = null,
    ): CarrierCollection {
        return DB::transaction(function () use ($store, $user, $carrier, $orderIds, $amount, $receivedOn, $reference, $note) {
            $orders = $this->forCarrier($store->orders(), $carrier)
                ->whereIn('id', $orderIds)
                ->where('status', Order::STATUS_DELIVERED)
                ->whereNull('carrier_collection_id')
                ->lockForUpdate()
                ->get(['id', 'total']);

            if ($orders->count() !== count(array_unique($orderIds))) {
                throw ValidationException::withMessages([
                    'order_ids' => 'في طلبات منهم اتحصّلت قبل كده أو مش تبع الشركة دي. حدّث الصفحة وجرّب تاني.',
                ]);
            }

            $collection = CarrierCollection::create([
                'store_id' => $store->id,
                'user_id' => $user?->id,
                'carrier' => $carrier === self::MANUAL ? null : $carrier,
                'amount' => round($amount, 2),
                'expected_amount' => round((float) $orders->sum('total'), 2),
                'orders_count' => $orders->count(),
                'received_on' => $receivedOn,
                'reference' => $reference,
                'note' => $note,
            ]);

            Order::query()->whereIn('id', $orders->pluck('id'))->update(['carrier_collection_id' => $collection->id]);

            return $collection;
        });
    }

    /** Undo a collection recorded by mistake: its orders are owed again. */
    public function undo(CarrierCollection $collection): void
    {
        DB::transaction(function () use ($collection) {
            Order::query()->where('carrier_collection_id', $collection->id)->update(['carrier_collection_id' => null]);
            $collection->delete();
        });
    }

    private function forCarrier($query, string $carrier)
    {
        return $carrier === self::MANUAL
            ? $query->whereNull('daman_carrier_name')
            : $query->where('daman_carrier_name', $carrier);
    }
}

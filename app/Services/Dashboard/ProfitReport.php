<?php

namespace App\Services\Dashboard;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What the merchant actually made, per product.
 *
 * Two decisions separate this from the revenue chart every platform ships:
 *
 *  1. Only delivered orders are income. A cash-on-delivery order that has not
 *     arrived is not money, and counting it is how a merchant believes a number
 *     that never reaches their hand.
 *
 *  2. A returned order is a LOSS, not a zero. The merchant paid to ship it out
 *     and paid again to get it back, and the goods spent a week in a van. A
 *     report that treats returns as "no sale" hides the single biggest cost in
 *     this market.
 */
class ProfitReport
{
    public function __construct(private readonly Store $store) {}

    /** @return array<string,mixed> */
    public function build(Carbon $from, Carbon $to): array
    {
        $delivered = $this->lines($from, $to, [Order::STATUS_DELIVERED]);
        $returned = $this->lines($from, $to, [Order::STATUS_RETURNED, Order::STATUS_CANCELLED]);

        $products = $this->byProduct($delivered, $returned);

        return [
            'totals' => $this->totals($products),
            'products' => $products->values()->all(),
            // How much of the catalogue has a cost recorded. Without it the
            // profit column is revenue wearing a different label, and the
            // merchant deserves to be told rather than shown a wrong number.
            'cost_coverage' => $this->costCoverage($delivered),
        ];
    }

    /**
     * @param  array<int,string>  $statuses
     * @return Collection<int,OrderItem>
     */
    private function lines(Carbon $from, Carbon $to, array $statuses): Collection
    {
        return OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('store_id', $this->store->id)
                ->whereIn('status', $statuses)
                ->whereBetween('created_at', [$from, $to]))
            // The product carries the cost. Left-joined in effect: a deleted
            // product still has to appear, or its history vanishes from the
            // report and the totals stop adding up.
            ->with('product:id,name,cost')
            ->get();
    }

    /**
     * @param  Collection<int,OrderItem>  $delivered
     * @param  Collection<int,OrderItem>  $returned
     * @return Collection<int,array<string,mixed>>
     */
    private function byProduct(Collection $delivered, Collection $returned): Collection
    {
        $rows = [];

        foreach ($delivered as $item) {
            $key = $item->product_id ?? 'deleted-' . $item->name;
            $rows[$key] ??= $this->blank($item);

            $unitCost = (float) ($item->product?->cost ?? 0);

            $rows[$key]['sold'] += $item->quantity;
            $rows[$key]['revenue'] += (float) $item->total;
            $rows[$key]['cost'] += $unitCost * $item->quantity;
            $rows[$key]['has_cost'] = $rows[$key]['has_cost'] || $unitCost > 0;
        }

        foreach ($returned as $item) {
            $key = $item->product_id ?? 'deleted-' . $item->name;
            $rows[$key] ??= $this->blank($item);

            $rows[$key]['returned'] += $item->quantity;

            /*
             | The cost of a return.
             |
             | Not the item's price — the merchant never collected that. What
             | they lost is the shipping they paid to send it and the shipping
             | to bring it back, which the carrier charges either way.
             */
            $rows[$key]['return_cost'] += (float) config('profit.return_cost_per_parcel', 0) * $item->quantity;
        }

        return collect($rows)
            ->map(function (array $row) {
                $row['profit'] = $row['revenue'] - $row['cost'] - $row['return_cost'];
                $row['margin'] = $row['revenue'] > 0
                    ? (int) round($row['profit'] / $row['revenue'] * 100)
                    : null;

                $settled = $row['sold'] + $row['returned'];
                $row['return_rate'] = $settled > 0
                    ? (int) round($row['returned'] / $settled * 100)
                    : 0;

                return $row;
            })
            // Worst margin first when it is negative, best revenue otherwise:
            // a product losing money is the one worth looking at today.
            ->sortBy(fn (array $row) => $row['profit'] < 0 ? $row['profit'] : PHP_INT_MAX - $row['revenue']);
    }

    /** @return array<string,mixed> */
    private function blank(OrderItem $item): array
    {
        return [
            'product_id' => $item->product_id,
            // The order line's own name, not the product's: a renamed product
            // must not rewrite what was sold last month.
            'name' => $item->name,
            'sold' => 0,
            'returned' => 0,
            'revenue' => 0.0,
            'cost' => 0.0,
            'return_cost' => 0.0,
            'has_cost' => false,
        ];
    }

    /** @param Collection<int,array<string,mixed>> $products */
    private function totals(Collection $products): array
    {
        return [
            'revenue' => round($products->sum('revenue'), 2),
            'cost' => round($products->sum('cost'), 2),
            'return_cost' => round($products->sum('return_cost'), 2),
            'profit' => round($products->sum('profit'), 2),
            'sold' => $products->sum('sold'),
            'returned' => $products->sum('returned'),
        ];
    }

    /** @param Collection<int,OrderItem> $delivered */
    private function costCoverage(Collection $delivered): array
    {
        $lines = $delivered->count();

        if ($lines === 0) {
            return ['known' => 0, 'total' => 0, 'percent' => 100];
        }

        $known = $delivered->filter(fn (OrderItem $i) => (float) ($i->product?->cost ?? 0) > 0)->count();

        return [
            'known' => $known,
            'total' => $lines,
            'percent' => (int) round($known / $lines * 100),
        ];
    }
}

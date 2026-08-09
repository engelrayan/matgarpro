<?php

namespace App\Services\Dashboard;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StorefrontEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everything the merchant dashboard shows, for one store over one window.
 *
 * Gathered in one place so the same definition of "revenue" or "profit" cannot
 * drift between a tile, a chart and a report. Two rules run through all of it:
 *
 *  1. Money means delivered. A pending cash-on-delivery order is a hope, not a
 *     sale, and a dashboard that counts it teaches the merchant to trust a
 *     number that never arrives in their hand.
 *  2. Cost is snapshotted per order line at sale time where possible, and
 *     falls back to the product's current cost. Profit is only shown for the
 *     part we can actually account for.
 */
class StoreInsights
{
    public function __construct(
        private readonly Store $store,
        private readonly CarbonImmutable $from,
        private readonly CarbonImmutable $to,
    ) {}

    /** @return array<string,mixed> */
    public function all(): array
    {
        return [
            'kpis' => $this->kpis(),
            'series' => $this->series(),
            'funnel' => $this->funnel(),
            'statuses' => $this->statusBreakdown(),
            'top_products' => $this->topProducts(),
            'low_stock' => $this->lowStock(),
        ];
    }

    /** @return array<string,mixed> */
    public function kpis(): array
    {
        $orders = $this->ordersInWindow();

        $total = (clone $orders)->count();
        $delivered = (clone $orders)->where('status', Order::STATUS_DELIVERED);
        $deliveredCount = (clone $delivered)->count();

        $revenue = (float) (clone $delivered)->sum('total');
        $cancelled = (clone $orders)->whereIn('status', [Order::STATUS_CANCELLED, Order::STATUS_RETURNED])->count();

        $views = $this->eventCount(StorefrontEvent::TYPE_VIEW);
        $orderEvents = $this->eventCount(StorefrontEvent::TYPE_ORDER);

        return [
            'orders' => $total,
            'revenue' => $revenue,
            'profit' => $this->profit(),
            // Average of what was actually sold, not of everything ordered:
            // an AOV inflated by orders that were never delivered is a lie the
            // merchant would price against.
            'aov' => $deliveredCount > 0 ? round($revenue / $deliveredCount, 2) : 0.0,
            'visits' => $views,
            'conversion' => $views > 0 ? round($orderEvents / $views * 100, 2) : 0.0,
            // Of the orders that reached a final state, how many arrived.
            'delivery_rate' => $this->deliveryRate(),
            'cancel_rate' => $total > 0 ? round($cancelled / $total * 100, 2) : 0.0,
            'pending' => $this->store->orders()->where('status', Order::STATUS_PENDING)->count(),
        ];
    }

    /**
     * Profit on delivered orders: revenue minus the cost of the goods.
     *
     * Lines whose product has no cost recorded contribute revenue but no cost,
     * which would overstate profit — so `profit_known_ratio` travels with the
     * number and the UI says how much of it is actually costed.
     *
     * @return array<string,float>
     */
    public function profit(): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.store_id', $this->store->id)
            ->where('orders.status', Order::STATUS_DELIVERED)
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.created_at', [$this->from, $this->to])
            ->selectRaw('SUM(order_items.total) AS revenue')
            ->selectRaw('SUM(CASE WHEN products.cost IS NULL THEN 0 ELSE products.cost * order_items.quantity END) AS cost')
            ->selectRaw('SUM(CASE WHEN products.cost IS NULL THEN 0 ELSE order_items.total END) AS costed_revenue')
            ->first();

        $revenue = (float) ($rows->revenue ?? 0);
        $cost = (float) ($rows->cost ?? 0);
        $costedRevenue = (float) ($rows->costed_revenue ?? 0);

        return [
            'amount' => round($revenue - $cost, 2),
            'cost' => round($cost, 2),
            'known_ratio' => $revenue > 0 ? round($costedRevenue / $revenue * 100) : 0.0,
        ];
    }

    /** Delivered ÷ (delivered + cancelled + returned). Open orders excluded. */
    public function deliveryRate(): float
    {
        $settled = $this->ordersInWindow()
            ->whereIn('status', [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED, Order::STATUS_RETURNED])
            ->count();

        if ($settled === 0) {
            return 0.0;
        }

        $delivered = $this->ordersInWindow()->where('status', Order::STATUS_DELIVERED)->count();

        return round($delivered / $settled * 100, 1);
    }

    /**
     * Daily orders, revenue and visits across the window.
     *
     * Every day in the range is present even when nothing happened — a chart
     * that silently skips empty days compresses a quiet week into a spike.
     *
     * @return array<int,array<string,mixed>>
     */
    public function series(): array
    {
        // keyBy on the fetched rows, not pluck() with a raw expression: pluck
        // takes its arguments as literal column names, so a CONCAT() there is
        // looked up as a column and blows up at runtime.
        $orders = $this->ordersInWindow()
            ->selectRaw('DATE(created_at) AS day')
            ->selectRaw('COUNT(*) AS order_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN total ELSE 0 END) AS revenue', [Order::STATUS_DELIVERED])
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $visits = StorefrontEvent::where('store_id', $this->store->id)
            ->where('type', StorefrontEvent::TYPE_VIEW)
            ->whereBetween('created_at', [$this->from, $this->to])
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS c')
            ->groupBy('day')
            ->pluck('c', 'day');

        $out = [];

        for ($day = $this->from->startOfDay(); $day->lte($this->to); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $row = $orders->get($key);

            $out[] = [
                'date' => $key,
                'label' => $day->translatedFormat('j M'),
                'orders' => (int) ($row->order_count ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
                'visits' => (int) ($visits[$key] ?? 0),
            ];
        }

        return $out;
    }

    /** @return array<string,int> */
    public function funnel(): array
    {
        return [
            'views' => $this->eventCount(StorefrontEvent::TYPE_VIEW),
            'checkout_starts' => $this->eventCount(StorefrontEvent::TYPE_CHECKOUT_START),
            'orders' => $this->eventCount(StorefrontEvent::TYPE_ORDER),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function statusBreakdown(): array
    {
        $counts = $this->ordersInWindow()
            ->selectRaw('status, COUNT(*) AS c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return collect(Order::STATUSES)
            ->map(fn (string $status) => [
                'status' => $status,
                'count' => (int) ($counts[$status] ?? 0),
            ])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values()
            ->all();
    }

    /** @return array<int,array<string,mixed>> */
    public function topProducts(int $limit = 5): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.store_id', $this->store->id)
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.created_at', [$this->from, $this->to])
            ->groupBy('order_items.name')
            ->selectRaw('order_items.name')
            ->selectRaw('SUM(order_items.quantity) AS qty')
            ->selectRaw('SUM(order_items.total) AS revenue')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                // The snapshotted line name, so a renamed or deleted product
                // still reads as what was actually sold.
                'name' => $row->name,
                'qty' => (int) $row->qty,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    /**
     * Products about to run out — ignoring any the merchant does not track.
     *
     * @return array<int,array<string,mixed>>
     */
    public function lowStock(int $threshold = 5, int $limit = 5): Collection|array
    {
        return $this->store->products()
            ->where('status', Product::STATUS_ACTIVE)
            ->where('track_stock', true)
            ->where('stock', '<=', $threshold)
            ->orderBy('stock')
            ->limit($limit)
            ->get(['id', 'name', 'stock'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'stock' => $p->stock,
            ])
            ->all();
    }

    private function ordersInWindow()
    {
        return $this->store->orders()->whereBetween('created_at', [$this->from, $this->to]);
    }

    private function eventCount(string $type): int
    {
        return StorefrontEvent::where('store_id', $this->store->id)
            ->where('type', $type)
            ->whereBetween('created_at', [$this->from, $this->to])
            ->count();
    }
}

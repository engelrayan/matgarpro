<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\StorefrontEvent;
use App\Models\StoreUsageEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Everything the platform panel shows, for one window.
 *
 * The merchant-facing {@see \App\Services\Dashboard\StoreInsights} answers
 * "how is my shop doing"; this answers "how is the platform doing", and the
 * two must not be confused:
 *
 *  1. **Demo stores are excluded from every headline.** The showrooms are ours,
 *     they carry seeded catalogues and they would inflate store counts and
 *     product counts with things nobody signed up for. They are reported
 *     separately so the number is still visible, just never mixed in.
 *  2. **GMV is not our revenue.** `gmv` is what merchants sold (delivered
 *     orders only, same rule as the merchant dashboard); `earnings` is what
 *     the platform actually charged, read from `store_usage_events` — the row
 *     that captured the price at the moment it was billed. Deriving our
 *     revenue from order totals instead would silently change history every
 *     time a plan is re-priced.
 */
class PlatformInsights
{
    public function __construct(
        private readonly CarbonImmutable $from,
        private readonly CarbonImmutable $to,
    ) {}

    /** @return array<string,mixed> */
    public function all(): array
    {
        return [
            'kpis' => $this->kpis(),
            'series' => $this->series(),
            'stores' => $this->storeBreakdown(),
            'orders' => $this->orderBreakdown(),
            'themes' => $this->themeUsage(),
            'domains' => $this->domainBreakdown(),
            'top_stores' => $this->topStores(),
            'newest_stores' => $this->newestStores(),
            'attention' => $this->needsAttention(),
        ];
    }

    /** @return array<string,mixed> */
    public function kpis(): array
    {
        $storeCounts = $this->storeBreakdown();

        return [
            'stores_total' => $storeCounts['total'],
            'stores_active' => $storeCounts['active'],
            'stores_new' => Store::query()->where('is_demo', false)
                ->whereBetween('created_at', [$this->from, $this->to])->count(),

            'merchants_total' => User::query()->count(),
            'merchants_new' => User::query()
                ->whereBetween('created_at', [$this->from, $this->to])->count(),

            'products_total' => $this->realStoreScope(Product::query(), 'store_id')->count(),

            'orders_total' => $this->ordersInWindow()->count(),
            'orders_all_time' => $this->realStoreScope(Order::query(), 'store_id')->count(),

            // What merchants sold, delivered only. A pending COD order is a
            // hope, and counting it here teaches us to forecast on nothing.
            'gmv' => (float) $this->ordersInWindow()
                ->where('status', Order::STATUS_DELIVERED)->sum('total'),

            // What we charged, from the billing ledger.
            'earnings' => $this->earnings($this->from, $this->to),
            'earnings_all_time' => $this->earnings(),

            'visits' => $this->realStoreScope(StorefrontEvent::query(), 'store_id')
                ->where('type', StorefrontEvent::TYPE_VIEW)
                ->whereBetween('created_at', [$this->from, $this->to])->count(),

            // Money sitting in merchant wallets: a liability, not income.
            'wallet_balance' => (float) Store::query()->where('is_demo', false)->sum('balance'),
            'wallets_negative' => Store::query()->where('is_demo', false)
                ->where('balance', '<', 0)->count(),

            'demo_stores' => Store::query()->where('is_demo', true)->count(),
        ];
    }

    /** Platform earnings over an optional window. */
    public function earnings(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): float
    {
        $query = $this->realStoreScope(StoreUsageEvent::query(), 'store_id');

        if ($from && $to) {
            $query->whereBetween('occurred_at', [$from, $to]);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Stores by status, plus the billing-side suspensions that a status of
     * `active` hides — a store can be active and still unable to sell because
     * its wallet ran dry.
     *
     * @return array<string,int>
     */
    public function storeBreakdown(): array
    {
        $byStatus = Store::query()->where('is_demo', false)
            ->selectRaw('status, COUNT(*) AS c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[Store::STATUS_ACTIVE] ?? 0),
            'draft' => (int) ($byStatus[Store::STATUS_DRAFT] ?? 0),
            'suspended' => (int) ($byStatus[Store::STATUS_SUSPENDED] ?? 0),
            'billing_suspended' => Store::query()->where('is_demo', false)
                ->where('billing_status', 'suspended')->count(),
        ];
    }

    /**
     * Orders in the window by status, across every real store.
     *
     * @return array<int,array<string,mixed>>
     */
    public function orderBreakdown(): array
    {
        $counts = $this->ordersInWindow()
            ->selectRaw('status, COUNT(*) AS c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return collect(Order::STATUSES)
            ->map(fn (string $status) => [
                'status' => $status,
                'label' => (new Order(['status' => $status]))->statusLabel(),
                'count' => (int) ($counts[$status] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * How many stores run each theme.
     *
     * A store that never opened the picker has no `theme` key at all, and it
     * renders with the platform default — so it is counted as the default
     * here too, rather than dropped. Anything else makes the numbers not add
     * up to the store count, which is the first thing anyone checks.
     *
     * @return array<int,array<string,mixed>>
     */
    public function themeUsage(): array
    {
        $default = (string) config('themes.default');

        $counts = Store::query()->where('is_demo', false)
            ->selectRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(settings, '$.theme')), ?) AS theme_key", [$default])
            ->selectRaw('COUNT(*) AS c')
            ->groupBy('theme_key')
            ->pluck('c', 'theme_key');

        $total = max(1, (int) $counts->sum());

        return collect((array) config('themes.themes'))
            ->map(fn (array $theme, string $key) => [
                'key' => $key,
                'name' => $theme['name'],
                'description' => $theme['description'],
                // Themes are palettes, so the panel can show one without
                // loading a storefront.
                'primary' => $theme['palette']['primary'],
                'accent' => $theme['palette']['accent'],
                'layout' => $theme['layout'],
                'stores' => (int) ($counts[$key] ?? 0),
                'share' => round(((int) ($counts[$key] ?? 0)) / $total * 100, 1),
            ])
            ->sortByDesc('stores')
            ->values()
            ->all();
    }

    /**
     * A theme key stored by a store that the platform no longer ships renders
     * as the default and would otherwise be invisible. Surfaced so a removed
     * theme is a decision, not a silent downgrade for whoever was using it.
     *
     * @return array<int,array<string,mixed>>
     */
    public function orphanThemes(): array
    {
        $known = array_keys((array) config('themes.themes'));

        return Store::query()->where('is_demo', false)
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(settings, '$.theme')) AS theme_key")
            ->selectRaw('COUNT(*) AS c')
            ->groupBy('theme_key')
            ->havingRaw('theme_key IS NOT NULL')
            ->get()
            ->reject(fn ($row) => in_array($row->theme_key, $known, true))
            ->map(fn ($row) => ['key' => $row->theme_key, 'stores' => (int) $row->c])
            ->values()
            ->all();
    }

    /** @return array<string,int> */
    public function domainBreakdown(): array
    {
        $counts = StoreDomain::query()
            ->selectRaw('status, COUNT(*) AS c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'total' => (int) $counts->sum(),
            'active' => (int) ($counts[StoreDomain::STATUS_ACTIVE] ?? 0),
            'pending' => (int) ($counts[StoreDomain::STATUS_PENDING] ?? 0),
            'failed' => (int) ($counts[StoreDomain::STATUS_FAILED] ?? 0),
        ];
    }

    /**
     * Daily platform activity. Every day in the range is present even when it
     * was quiet — skipping empty days compresses a dead week into a spike.
     *
     * @return array<int,array<string,mixed>>
     */
    public function series(): array
    {
        $orders = $this->ordersInWindow()
            ->selectRaw('DATE(orders.created_at) AS day')
            ->selectRaw('COUNT(*) AS order_count')
            ->selectRaw('SUM(CASE WHEN orders.status = ? THEN orders.total ELSE 0 END) AS gmv', [Order::STATUS_DELIVERED])
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $earnings = $this->realStoreScope(StoreUsageEvent::query(), 'store_id')
            ->whereBetween('occurred_at', [$this->from, $this->to])
            ->selectRaw('DATE(occurred_at) AS day, SUM(amount) AS total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $signups = Store::query()->where('is_demo', false)
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
                'gmv' => (float) ($row->gmv ?? 0),
                'earnings' => (float) ($earnings[$key] ?? 0),
                'stores' => (int) ($signups[$key] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Busiest stores in the window, by delivered revenue.
     *
     * @return array<int,array<string,mixed>>
     */
    public function topStores(int $limit = 8): array
    {
        return DB::table('orders')
            ->join('stores', 'stores.id', '=', 'orders.store_id')
            ->whereNull('orders.deleted_at')
            ->whereNull('stores.deleted_at')
            ->where('stores.is_demo', false)
            ->whereBetween('orders.created_at', [$this->from, $this->to])
            ->groupBy('stores.id', 'stores.name', 'stores.slug')
            ->selectRaw('stores.id, stores.name, stores.slug')
            ->selectRaw('COUNT(*) AS orders')
            ->selectRaw('SUM(CASE WHEN orders.status = ? THEN orders.total ELSE 0 END) AS revenue', [Order::STATUS_DELIVERED])
            ->orderByDesc('revenue')
            ->orderByDesc('orders')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'slug' => $row->slug,
                'orders' => (int) $row->orders,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    /** @return array<int,array<string,mixed>> */
    public function newestStores(int $limit = 6): array
    {
        return Store::query()->where('is_demo', false)
            ->with('user:id,name,email')
            ->withCount('orders')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Store $store) => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status,
                'orders_count' => $store->orders_count,
                'merchant' => $store->user?->name,
                'created_at' => $store->created_at->diffForHumans(),
            ])
            ->all();
    }

    /**
     * The short list an operator should actually act on today.
     *
     * Deliberately three narrow queries rather than one "problems" table: each
     * has a different fix, and lumping them together produces a list nobody
     * knows what to do with.
     *
     * @return array<string,mixed>
     */
    public function needsAttention(): array
    {
        return [
            // Selling is already blocked for these.
            'overdrawn' => Store::query()->where('is_demo', false)
                ->where('balance', '<', 0)
                ->orderBy('balance')
                ->limit(6)
                ->get(['id', 'name', 'slug', 'balance'])
                ->map(fn (Store $s) => [
                    'id' => $s->id, 'name' => $s->name, 'slug' => $s->slug,
                    'balance' => (float) $s->balance,
                ])->all(),

            // DNS has been wrong long enough that we stopped retrying.
            'failed_domains' => StoreDomain::query()
                ->where('status', StoreDomain::STATUS_FAILED)
                ->with('store:id,name')
                ->latest('last_checked_at')
                ->limit(6)
                ->get()
                ->map(fn (StoreDomain $d) => [
                    'id' => $d->id, 'domain' => $d->domain,
                    'store' => $d->store?->name, 'store_id' => $d->store_id,
                    'error' => $d->last_error,
                ])->all(),

            // Signed up, never launched. The cheapest merchants to save.
            'empty_stores' => Store::query()->where('is_demo', false)
                ->where('created_at', '<', now()->subDays(3))
                ->whereDoesntHave('products')
                ->latest('id')
                ->limit(6)
                ->get(['id', 'name', 'slug', 'created_at'])
                ->map(fn (Store $s) => [
                    'id' => $s->id, 'name' => $s->name, 'slug' => $s->slug,
                    'created_at' => $s->created_at->diffForHumans(),
                ])->all(),
        ];
    }

    // -------------------------------------------------------------------------

    /** Orders in the window, demo shops excluded. */
    private function ordersInWindow()
    {
        return Order::query()
            ->whereBetween('orders.created_at', [$this->from, $this->to])
            ->whereIn('orders.store_id', $this->realStoreIds());
    }

    /**
     * Restrict any store-owned query to stores that belong to real merchants.
     *
     * A sub-query rather than a join so callers keep their own column names —
     * a join would make `status` ambiguous on half of these tables.
     */
    private function realStoreScope($query, string $column)
    {
        return $query->whereIn($column, $this->realStoreIds());
    }

    private function realStoreIds()
    {
        return Store::query()->where('is_demo', false)->select('id');
    }
}

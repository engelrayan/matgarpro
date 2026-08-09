<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreUsageEvent;
use App\Services\Admin\PlatformInsights;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The numbers the panel reports. Two rules are load-bearing and both are
 * asserted here: showrooms never count as merchants' stores, and platform
 * earnings come from the billing ledger rather than from order totals.
 */
class AdminInsightsTest extends TestCase
{
    use RefreshDatabase;

    private function insights(): PlatformInsights
    {
        return new PlatformInsights(
            CarbonImmutable::now()->subDays(29)->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
        );
    }

    public function test_demo_stores_are_kept_out_of_the_headline_counts(): void
    {
        Store::factory()->count(3)->create();
        Store::factory()->count(2)->create(['is_demo' => true]);

        $kpis = $this->insights()->kpis();

        $this->assertSame(3, $kpis['stores_total']);
        $this->assertSame(2, $kpis['demo_stores']);
    }

    public function test_gmv_counts_delivered_orders_only(): void
    {
        $store = Store::factory()->create();

        Order::factory()->for($store)->create(['status' => Order::STATUS_DELIVERED, 'total' => 250]);
        Order::factory()->for($store)->create(['status' => Order::STATUS_PENDING, 'total' => 900]);
        Order::factory()->for($store)->create(['status' => Order::STATUS_CANCELLED, 'total' => 400]);

        $kpis = $this->insights()->kpis();

        $this->assertSame(3, $kpis['orders_total']);
        $this->assertSame(250.0, $kpis['gmv']);
    }

    public function test_platform_earnings_come_from_the_billing_ledger(): void
    {
        $store = Store::factory()->create();

        // A big order the merchant made, and the small fee we actually charged.
        Order::factory()->for($store)->create(['status' => Order::STATUS_DELIVERED, 'total' => 1000]);

        StoreUsageEvent::create([
            'store_id' => $store->id,
            'type' => StoreUsageEvent::TYPE_ORDER,
            'billable_type' => Order::class,
            'billable_id' => 1,
            'quantity' => 1,
            'unit_price' => 1.5,
            'amount' => 1.5,
            'price_source' => 'plan',
            'occurred_at' => now(),
        ]);

        $kpis = $this->insights()->kpis();

        $this->assertSame(1000.0, $kpis['gmv']);
        $this->assertSame(1.5, $kpis['earnings']);
    }

    public function test_stores_with_no_saved_theme_count_towards_the_default(): void
    {
        Store::factory()->count(2)->create(['settings' => null]);
        Store::factory()->create(['settings' => ['theme' => 'noir']]);

        $usage = collect($this->insights()->themeUsage())->keyBy('key');

        $this->assertSame(2, $usage[config('themes.default')]['stores']);
        $this->assertSame(1, $usage['noir']['stores']);
        // Every store lands in exactly one bucket — the shares must add up.
        $this->assertSame(3, collect($usage)->sum('stores'));
    }

    public function test_a_theme_we_no_longer_ship_is_surfaced_rather_than_hidden(): void
    {
        Store::factory()->create(['settings' => ['theme' => 'retired-theme']]);

        $orphans = $this->insights()->orphanThemes();

        $this->assertSame('retired-theme', $orphans[0]['key']);
        $this->assertSame(1, $orphans[0]['stores']);
    }

    public function test_the_series_has_a_point_for_every_day_in_the_window(): void
    {
        $series = $this->insights()->series();

        $this->assertCount(30, $series);
    }

    public function test_the_overview_renders_for_an_operator(): void
    {
        Store::factory()->count(2)->create();

        $this->actingAs(Admin::factory()->super()->create(), 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Overview')
                ->where('insights.kpis.stores_total', 2));
    }
}

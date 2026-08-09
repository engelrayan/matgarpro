<?php

namespace Tests\Feature\Dashboard;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\StorefrontEvent;
use App\Models\User;
use App\Services\Dashboard\StoreInsights;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private StoreInsights $insights;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create(['slug' => 'mahmoud']);

        $this->insights = new StoreInsights(
            $this->store,
            CarbonImmutable::now()->subDays(6)->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
        );
    }

    private function order(string $status, float $total): Order
    {
        return Order::factory()->for($this->store)->create([
            'status' => $status,
            'total' => $total,
            'subtotal' => $total,
        ]);
    }

    /**
     * A pending cash-on-delivery order is a hope, not money. Counting it as
     * revenue teaches the merchant to trust a number that never arrives.
     */
    public function test_revenue_counts_delivered_orders_only(): void
    {
        $this->order(Order::STATUS_DELIVERED, 500);
        $this->order(Order::STATUS_PENDING, 900);
        $this->order(Order::STATUS_CANCELLED, 700);

        $kpis = $this->insights->kpis();

        $this->assertSame(500.0, $kpis['revenue']);
        $this->assertSame(3, $kpis['orders']);
    }

    /** Average order value is of what was sold, not of everything ordered. */
    public function test_aov_is_computed_over_delivered_orders(): void
    {
        $this->order(Order::STATUS_DELIVERED, 400);
        $this->order(Order::STATUS_DELIVERED, 600);
        $this->order(Order::STATUS_CANCELLED, 10_000);

        $this->assertSame(500.0, $this->insights->kpis()['aov']);
    }

    public function test_delivery_rate_ignores_orders_still_in_flight(): void
    {
        $this->order(Order::STATUS_DELIVERED, 100);
        $this->order(Order::STATUS_DELIVERED, 100);
        $this->order(Order::STATUS_CANCELLED, 100);
        // Still open — must not count against the merchant either way.
        $this->order(Order::STATUS_SHIPPED, 100);

        $this->assertSame(66.7, $this->insights->deliveryRate());
    }

    public function test_profit_subtracts_product_cost(): void
    {
        $product = Product::factory()->for($this->store)->create(['price' => 300, 'cost' => 100]);
        $order = $this->order(Order::STATUS_DELIVERED, 600);

        OrderItem::factory()->for($order)->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => 300,
            'quantity' => 2,
            'total' => 600,
        ]);

        $profit = $this->insights->profit();

        $this->assertSame(400.0, $profit['amount']);
        $this->assertSame(200.0, $profit['cost']);
        $this->assertSame(100.0, $profit['known_ratio']);
    }

    /**
     * Products with no cost recorded contribute revenue but no cost, which
     * would read as pure profit. The ratio is what lets the UI say so.
     */
    public function test_profit_reports_how_much_of_it_is_actually_costed(): void
    {
        $costed = Product::factory()->for($this->store)->create(['cost' => 100]);
        $uncosted = Product::factory()->for($this->store)->create(['cost' => null]);

        $order = $this->order(Order::STATUS_DELIVERED, 1000);

        OrderItem::factory()->for($order)->create([
            'product_id' => $costed->id, 'name' => 'A',
            'unit_price' => 500, 'quantity' => 1, 'total' => 500,
        ]);
        OrderItem::factory()->for($order)->create([
            'product_id' => $uncosted->id, 'name' => 'B',
            'unit_price' => 500, 'quantity' => 1, 'total' => 500,
        ]);

        $this->assertSame(50.0, $this->insights->profit()['known_ratio']);
    }

    public function test_the_series_includes_days_with_nothing_on_them(): void
    {
        $series = $this->insights->series();

        $this->assertCount(7, $series, 'a 7-day window is 7 points even when idle');
        $this->assertSame(0, $series[0]['orders']);
    }

    public function test_funnel_counts_each_stage(): void
    {
        foreach ([StorefrontEvent::TYPE_VIEW, StorefrontEvent::TYPE_VIEW, StorefrontEvent::TYPE_CHECKOUT_START] as $type) {
            StorefrontEvent::create([
                'store_id' => $this->store->id,
                'type' => $type,
                'visitor_id' => uniqid(),
            ]);
        }

        $funnel = $this->insights->funnel();

        $this->assertSame(2, $funnel['views']);
        $this->assertSame(1, $funnel['checkout_starts']);
        $this->assertSame(0, $funnel['orders']);
    }

    public function test_low_stock_skips_products_that_do_not_track_stock(): void
    {
        Product::factory()->for($this->store)->create(['name' => 'قميص', 'stock' => 2, 'track_stock' => true]);
        Product::factory()->for($this->store)->create(['name' => 'خدمة', 'stock' => 0, 'track_stock' => false]);

        $low = $this->insights->lowStock();

        $this->assertCount(1, $low);
        $this->assertSame('قميص', $low[0]['name']);
    }

    /** One store's numbers must never include another's. */
    public function test_insights_are_scoped_to_the_store(): void
    {
        $this->order(Order::STATUS_DELIVERED, 500);

        $other = Store::factory()->create();
        Order::factory()->for($other)->create(['status' => Order::STATUS_DELIVERED, 'total' => 9_999, 'subtotal' => 9_999]);

        $this->assertSame(500.0, $this->insights->kpis()['revenue']);
    }

    public function test_the_dashboard_renders_with_a_range(): void
    {
        $user = User::factory()->create();
        Store::factory()->for($user)->create();

        $this->actingAs($user)
            ->get('http://localhost/dashboard?range=30d')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('range', '30d')
                ->has('insights.kpis')
                ->has('insights.series', 30));
    }

    /** An unknown range must fall back, not blow up or query a silly window. */
    public function test_an_unknown_range_falls_back_to_the_default(): void
    {
        $user = User::factory()->create();
        Store::factory()->for($user)->create();

        $this->actingAs($user)
            ->get('http://localhost/dashboard?range=all-time')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('range', '7d')->has('insights.series', 7));
    }
}

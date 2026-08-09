<?php

namespace Tests\Feature\Reports;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The report exists to contradict the revenue chart. These assert the two
 * things that make it different: unarrived orders are not income, and a return
 * is a loss rather than a zero.
 */
class ProfitReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('profit.return_cost_per_parcel', 60);

        $this->user = User::factory()->create();
        $this->store = Store::factory()->for($this->user)->create();
    }

    private function sell(Product $product, string $status, int $qty = 1, int $number = null): Order
    {
        static $seq = 0;

        $order = Order::factory()->for($this->store)->create([
            'number' => $number ?? ++$seq,
            'status' => $status,
            'subtotal' => $product->price * $qty,
            'total' => $product->price * $qty,
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => $qty,
            'total' => $product->price * $qty,
        ]);

        return $order;
    }

    /** @return array<string,mixed> */
    private function report(): array
    {
        return $this->actingAs($this->user)
            ->get('http://localhost/reports/profit?range=all')
            ->assertOk()
            ->viewData('page')['props']['report'];
    }

    // ── The two decisions that matter ───────────────────────────────────────

    /**
     * A cash-on-delivery order that has not arrived is not money. Counting it
     * is how a merchant believes a number that never reaches their hand.
     */
    public function test_only_delivered_orders_count_as_income(): void
    {
        $product = Product::factory()->for($this->store)->create(['price' => 400, 'cost' => 250]);

        $this->sell($product, Order::STATUS_DELIVERED);
        $this->sell($product, Order::STATUS_PENDING);
        $this->sell($product, Order::STATUS_SHIPPED);

        $this->assertSame(400.0, $this->report()['totals']['revenue']);
    }

    /**
     * The whole point. The merchant paid to ship it out and again to get it
     * back — a report that shows zero hides the biggest cost in this market.
     */
    public function test_a_return_is_a_loss_not_a_zero(): void
    {
        $product = Product::factory()->for($this->store)->create(['price' => 400, 'cost' => 250]);

        $this->sell($product, Order::STATUS_DELIVERED);
        $this->sell($product, Order::STATUS_RETURNED);

        $totals = $this->report()['totals'];

        $this->assertSame(400.0, $totals['revenue']);
        $this->assertSame(250.0, $totals['cost']);
        $this->assertSame(60.0, $totals['return_cost']);
        // 400 − 250 − 60
        $this->assertSame(90.0, $totals['profit']);
    }

    public function test_returns_can_push_a_product_into_a_loss(): void
    {
        $product = Product::factory()->for($this->store)->create(['name' => 'خسران', 'price' => 400, 'cost' => 350]);

        $this->sell($product, Order::STATUS_DELIVERED);
        $this->sell($product, Order::STATUS_RETURNED);
        $this->sell($product, Order::STATUS_RETURNED);

        $row = $this->report()['products'][0];

        // 400 − 350 − 120
        $this->assertSame(-70.0, $row['profit']);
        $this->assertSame(2, $row['returned']);
        $this->assertSame(67, $row['return_rate']);
    }

    /** A product losing money is the one worth looking at today. */
    public function test_loss_making_products_are_listed_first(): void
    {
        $good = Product::factory()->for($this->store)->create(['name' => 'كويس', 'price' => 500, 'cost' => 100]);
        $bad = Product::factory()->for($this->store)->create(['name' => 'خسران', 'price' => 200, 'cost' => 180]);

        $this->sell($good, Order::STATUS_DELIVERED);
        $this->sell($bad, Order::STATUS_DELIVERED);
        $this->sell($bad, Order::STATUS_RETURNED);

        $this->assertSame('خسران', $this->report()['products'][0]['name']);
    }

    // ── Honesty about missing data ──────────────────────────────────────────

    /**
     * Without costs the profit column is revenue wearing a different label.
     * The merchant has to be told, not shown a confident wrong number.
     */
    public function test_missing_costs_are_reported(): void
    {
        $withCost = Product::factory()->for($this->store)->create(['price' => 400, 'cost' => 250]);
        $without = Product::factory()->for($this->store)->create(['price' => 400, 'cost' => null]);

        $this->sell($withCost, Order::STATUS_DELIVERED);
        $this->sell($without, Order::STATUS_DELIVERED);

        $coverage = $this->report()['cost_coverage'];

        $this->assertSame(1, $coverage['known']);
        $this->assertSame(2, $coverage['total']);
        $this->assertSame(50, $coverage['percent']);
    }

    public function test_a_product_without_a_cost_is_flagged_on_its_row(): void
    {
        $product = Product::factory()->for($this->store)->create(['price' => 400, 'cost' => null]);

        $this->sell($product, Order::STATUS_DELIVERED);

        $this->assertFalse($this->report()['products'][0]['has_cost']);
    }

    // ── Details that would quietly corrupt the numbers ──────────────────────

    /** Renaming a product must not rewrite what was sold last month. */
    public function test_rows_use_the_name_recorded_on_the_order_line(): void
    {
        $product = Product::factory()->for($this->store)->create(['name' => 'الاسم القديم', 'price' => 400, 'cost' => 100]);

        $this->sell($product, Order::STATUS_DELIVERED);
        $product->update(['name' => 'الاسم الجديد']);

        $this->assertSame('الاسم القديم', $this->report()['products'][0]['name']);
    }

    public function test_another_stores_sales_never_appear(): void
    {
        $theirs = Store::factory()->create();
        $product = Product::factory()->for($theirs)->create(['name' => 'مش بتاعي', 'price' => 400]);

        Order::factory()->for($theirs)->create(['number' => 1, 'status' => Order::STATUS_DELIVERED])
            ->items()->create([
                'product_id' => $product->id, 'name' => 'مش بتاعي',
                'unit_price' => 400, 'quantity' => 1, 'total' => 400,
            ]);

        $this->assertSame(0.0, $this->report()['totals']['revenue']);
    }

    public function test_the_range_filter_narrows_the_window(): void
    {
        $product = Product::factory()->for($this->store)->create(['price' => 400, 'cost' => 100]);

        $old = $this->sell($product, Order::STATUS_DELIVERED);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();

        $this->sell($product, Order::STATUS_DELIVERED);

        $recent = $this->actingAs($this->user)
            ->get('http://localhost/reports/profit?range=7d')
            ->viewData('page')['props']['report'];

        $this->assertSame(400.0, $recent['totals']['revenue']);
        $this->assertSame(800.0, $this->report()['totals']['revenue']);
    }

    public function test_the_page_renders(): void
    {
        $this->actingAs($this->user)
            ->get('http://localhost/reports/profit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('reports/Profit'));
    }

    /**
     * With nothing sold the page has to render an empty product list rather
     * than fail on missing totals — the empty state is drawn client-side from
     * exactly this shape.
     */
    public function test_a_store_with_no_sales_still_gets_a_usable_payload(): void
    {
        $report = $this->report();

        $this->assertSame([], $report['products']);
        $this->assertSame(0.0, $report['totals']['profit']);
        $this->assertSame(100, $report['cost_coverage']['percent']);
    }
}

<?php

namespace Tests\Feature\Orders;

use App\Models\CustomerReputation;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checkout takes units off the shelf; a cancelled or returned order must put
 * them back, or a shop with a normal return rate slowly "sells out" of stock
 * it is still holding.
 */
class StockReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->store = Store::factory()->for($this->user)->create();
    }

    private function orderOf(Product $product, int $quantity, ?ProductVariant $variant = null): Order
    {
        $order = Order::factory()->for($this->store)->create(['status' => Order::STATUS_CONFIRMED]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'name' => $product->name,
            'unit_price' => 100,
            'quantity' => $quantity,
            'total' => 100 * $quantity,
        ]);

        return $order;
    }

    public function test_cancelling_an_order_puts_its_units_back(): void
    {
        $product = Product::factory()->for($this->store)->create(['track_stock' => true, 'stock' => 8]);
        $order = $this->orderOf($product, 2);

        $order->update(['status' => Order::STATUS_CANCELLED]);

        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_a_variant_gets_its_own_units_back(): void
    {
        $product = Product::factory()->for($this->store)->create(['track_stock' => true, 'stock' => 0]);
        $variant = $product->variants()->create(['price' => 100, 'stock' => 3, 'options' => ['المقاس' => 'L']]);
        $order = $this->orderOf($product, 2, $variant);

        $order->update(['status' => Order::STATUS_RETURNED]);

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertSame(0, $product->fresh()->stock);
    }

    /** Un-cancelling takes them again; cancelled → returned moves nothing. */
    public function test_moving_between_released_statuses_does_not_count_twice(): void
    {
        $product = Product::factory()->for($this->store)->create(['track_stock' => true, 'stock' => 8]);
        $order = $this->orderOf($product, 2);

        $order->update(['status' => Order::STATUS_CANCELLED]);
        $order->update(['status' => Order::STATUS_RETURNED]);
        $this->assertSame(10, $product->fresh()->stock);

        $order->update(['status' => Order::STATUS_CONFIRMED]);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_an_untracked_product_is_left_alone(): void
    {
        $product = Product::factory()->for($this->store)->create(['track_stock' => false, 'stock' => 0]);
        $order = $this->orderOf($product, 2);

        $order->update(['status' => Order::STATUS_CANCELLED]);

        $this->assertSame(0, $product->fresh()->stock);
    }

    /**
     * The bulk action used a single UPDATE, which skips model events — so the
     * most-used button on the orders screen touched neither stock nor the
     * customer's delivery record.
     */
    public function test_the_bulk_action_returns_stock_and_records_the_outcome(): void
    {
        $product = Product::factory()->for($this->store)->create(['track_stock' => true, 'stock' => 8]);
        $order = $this->orderOf($product, 2);

        $this->actingAs($this->user)
            ->patch('http://localhost/orders-bulk/status', ['ids' => [$order->id], 'status' => Order::STATUS_RETURNED])
            ->assertSessionHasNoErrors();

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(1, CustomerReputation::where('phone', $order->customer_phone)->value('refused'));
    }
}

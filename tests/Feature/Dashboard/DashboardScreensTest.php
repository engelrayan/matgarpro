<?php

namespace Tests\Feature\Dashboard;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every dashboard screen renders with real data in it.
 *
 * These catch the failures unit tests never see: a page that 500s because a
 * relation was not eager-loaded, or an Inertia prop the component expects and
 * the controller stopped sending.
 */
class DashboardScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->store = Store::factory()->for($this->user)->create(['currency' => 'EGP']);
    }

    public function test_the_products_list_renders(): void
    {
        Product::factory()->for($this->store)->create(['name' => 'قميص قطن']);

        $this->actingAs($this->user)
            ->get('http://localhost/products')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('products/Index')
                ->where('products.data.0.name', 'قميص قطن')
                ->has('products.data.0.url'));
    }

    public function test_the_new_product_screen_renders(): void
    {
        $this->actingAs($this->user)
            ->get('http://localhost/products/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('products/Form')
                ->where('product', null)
                ->where('currency', 'EGP'));
    }

    /** The edit screen has to hand the component its options AND its variants. */
    public function test_the_edit_screen_carries_options_and_variants(): void
    {
        $product = Product::factory()->for($this->store)->withVariants()->create();

        $this->actingAs($this->user)
            ->get("http://localhost/products/{$product->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('products/Form')
                ->has('product.options', 2)
                ->has('product.variants', 4)
                ->has('product.images'));
    }

    public function test_the_orders_list_renders_with_status_counts(): void
    {
        $order = $this->store->orders()->create([
            'number' => 1, 'customer_name' => 'محمود ممدوح', 'customer_phone' => '01006262330',
            'governorate' => 'القاهرة', 'address' => 'شارع الهرم',
            'subtotal' => 399, 'total' => 399, 'status' => Order::STATUS_PENDING,
        ]);
        $order->items()->create([
            'name' => 'قميص قطن', 'unit_price' => 399, 'quantity' => 1, 'total' => 399,
        ]);

        $this->actingAs($this->user)
            ->get('http://localhost/orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('orders/Index')
                ->where('orders.data.0.customer_name', 'محمود ممدوح')
                ->where('orders.data.0.status', Order::STATUS_PENDING)
                ->where('counts.pending', 1)
                ->where('counts.all', 1)
                // The grid shows one line per order, so the items arrive as a
                // summary string rather than a nested collection.
                ->where('orders.data.0.items_summary', '1× قميص قطن'));
    }

    public function test_a_merchant_can_move_an_order_along(): void
    {
        $order = $this->store->orders()->create([
            'number' => 1, 'customer_name' => 'محمود', 'customer_phone' => '01006262330',
            'address' => 'القاهرة', 'subtotal' => 399, 'total' => 399,
        ]);

        $this->actingAs($this->user)
            ->patch("http://localhost/orders/{$order->id}/status", ['status' => Order::STATUS_CONFIRMED])
            ->assertRedirect();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    public function test_a_merchant_cannot_move_another_merchants_order(): void
    {
        $theirs = Store::factory()->create()->orders()->create([
            'number' => 1, 'customer_name' => 'أحمد', 'customer_phone' => '01111111111',
            'address' => 'الجيزة', 'subtotal' => 100, 'total' => 100,
        ]);

        $this->actingAs($this->user)
            ->patch("http://localhost/orders/{$theirs->id}/status", ['status' => Order::STATUS_CONFIRMED])
            ->assertForbidden();

        $this->assertSame(Order::STATUS_PENDING, $theirs->fresh()->status);
    }

    public function test_the_marketing_page_is_public_and_needs_no_app_javascript(): void
    {
        $response = $this->get('http://localhost/');

        $response->assertOk()
            ->assertSee('متجرك الإلكتروني جاهز في')
            // The two claims the page is built around.
            ->assertSee('شبكة سندباد')
            ->assertSee('٣ شهور مجانية');

        $this->assertStringNotContainsString(
            'data-page=',
            $response->getContent(),
            'the landing page must not boot Inertia',
        );
    }
}

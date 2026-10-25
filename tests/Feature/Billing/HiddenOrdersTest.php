<?php

namespace Tests\Feature\Billing;

use App\Actions\Orders\ShipOrdersViaDaman;
use App\Jobs\SendWhatsappOrderConfirmation;
use App\Models\AbandonedCart;
use App\Models\Admin;
use App\Models\BillingPlan;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreTopupRequest;
use App\Models\StoreUsageEvent;
use App\Models\StoreWhatsappIntegration;
use App\Models\User;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Past the credit floor a store keeps selling, but the merchant sees new
 * orders without their customers until a top-up covers them — then they open,
 * oldest first, charged one by one, and their WhatsApp confirmations go out.
 */
class HiddenOrdersTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing.overdraft_limit', 25);

        $this->merchant = User::factory()->create();
        $this->store = Store::factory()->for($this->merchant)->create([
            'slug' => 'sara',
            'billing_plan_id' => BillingPlan::factory()->create(['price_per_order' => 0.75])->id,
        ]);
        $this->product = Product::factory()->for($this->store)->create(['price' => 300, 'stock' => 50]);
    }

    private function balance(float $balance): void
    {
        app(WalletService::class)->debit($this->store, abs($balance), description: 'رصيد سالب للاختبار');
        $this->store->refresh();
    }

    private function order(string $phone = '01006262330'): TestResponse
    {
        return $this->post('http://' . $this->store->platformHost() . '/checkout', [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'customer_name' => 'محمود ممدوح',
            'customer_phone' => $phone,
            'governorate' => 'القاهرة',
            'address' => 'شارع الهرم، عمارة ٥',
        ]);
    }

    private function hiddenOrder(): Order
    {
        $this->balance(-30);
        $this->order()->assertRedirect();

        return Order::latest('id')->first();
    }

    // ── Checkout ─────────────────────────────────────────────────────────

    public function test_past_the_floor_the_order_is_taken_but_hidden_and_unbilled(): void
    {
        $this->balance(-30);

        $this->order()->assertRedirect();

        $order = Order::sole();
        $this->assertTrue($order->isLocked());
        $this->assertSame(49, $this->product->fresh()->stock, 'the parcel is real: stock is taken');
        $this->assertSame(0, StoreUsageEvent::where('type', StoreUsageEvent::TYPE_ORDER)->count());
        $this->assertSame('-30.00', (string) $this->store->refresh()->balance);
    }

    /**
     * The platform's setting: no credit. A merchant who never topped up sees
     * their first order arrive hidden, and topping up is what opens it.
     */
    public function test_with_no_credit_a_wallet_at_zero_hides_the_very_first_order(): void
    {
        config()->set('billing.overdraft_limit', 0);
        $this->assertSame('0.00', (string) $this->store->refresh()->balance);

        $this->order()->assertRedirect();

        $order = Order::sole();
        $this->assertTrue($order->isLocked());
        $this->assertSame(0, StoreUsageEvent::where('type', StoreUsageEvent::TYPE_ORDER)->count());

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->post("http://localhost/admin/stores/{$this->store->id}/wallet", ['direction' => 'credit', 'amount' => 100, 'note' => 'شحن'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($order->fresh()->isLocked());
    }

    public function test_a_free_plan_never_hides_an_order_whatever_the_balance(): void
    {
        config()->set('billing.overdraft_limit', 0);
        $this->store->update(['billing_plan_id' => BillingPlan::factory()->create(['price_per_order' => 0])->id]);

        $this->order()->assertRedirect();

        $this->assertFalse(Order::sole()->isLocked());
    }

    public function test_inside_the_buffer_the_order_is_visible_and_billed(): void
    {
        $this->balance(-24);

        $this->order()->assertRedirect();

        $this->assertFalse(Order::sole()->isLocked());
        $this->assertSame('-24.75', (string) $this->store->refresh()->balance);
    }

    public function test_a_hidden_order_does_not_message_the_customer(): void
    {
        Queue::fake();
        config(['services.whatsapp.waha.url' => 'http://waha.test', 'services.whatsapp.waha.api_key' => 'waha-key', 'services.whatsapp.waha.webhook_secret' => 'secret']);
        $this->store->whatsappIntegration()->create([
            'driver' => StoreWhatsappIntegration::DRIVER_WAHA,
            'credentials' => ['status' => 'WORKING'],
            'is_active' => true, 'auto_send' => true,
            'webhook_token' => StoreWhatsappIntegration::newToken(),
            'verify_token' => StoreWhatsappIntegration::newToken(),
            'connected_at' => now(),
        ]);

        $this->hiddenOrder();

        Queue::assertNotPushed(SendWhatsappOrderConfirmation::class);
    }

    // ── What the merchant sees ───────────────────────────────────────────

    public function test_the_list_shows_the_order_without_its_customer(): void
    {
        $order = $this->hiddenOrder();

        $this->actingAs($this->merchant)
            ->get('http://localhost/orders')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('locked.count', 1)
                ->where('locked.value', 300)
                ->where('orders.data.0.number', $order->number)
                ->where('orders.data.0.locked', true)
                ->where('orders.data.0.customer_name', null)
                ->where('orders.data.0.customer_phone', null)
                ->where('orders.data.0.address', null)
                ->where('orders.data.0.governorate', 'القاهرة'));
    }

    public function test_a_hidden_order_is_found_by_its_number_not_its_customer(): void
    {
        $order = $this->hiddenOrder();

        $this->actingAs($this->merchant)->get('http://localhost/orders?q=01006262330')
            ->assertInertia(fn ($page) => $page->where('orders.total', 0));

        $this->actingAs($this->merchant)->get('http://localhost/orders?q=' . $order->number)
            ->assertInertia(fn ($page) => $page->where('orders.total', 1));
    }

    public function test_the_order_page_hides_the_customer_and_their_history(): void
    {
        $order = $this->hiddenOrder();

        $this->actingAs($this->merchant)
            ->get("http://localhost/orders/{$order->id}")
            ->assertOk()
            ->assertDontSee('01006262330')
            ->assertInertia(fn ($page) => $page
                ->where('order.locked', true)
                ->where('order.customer_name', null)
                ->where('order.customer_phone', null)
                ->where('order.ip', null)
                ->where('customer_history.orders', 0)
                ->where('network_reputation', null));
    }

    public function test_the_dashboard_counts_hidden_orders_and_hides_their_names(): void
    {
        $this->hiddenOrder();

        $this->actingAs($this->merchant)
            ->get('http://localhost/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('billing.locked.count', 1)
                ->where('recent.0.locked', true)
                ->where('recent.0.customer_name', null));
    }

    public function test_the_recovered_cart_does_not_give_the_customer_away(): void
    {
        $order = $this->hiddenOrder();
        AbandonedCart::create([
            'store_id' => $this->store->id, 'product_id' => $this->product->id, 'quantity' => 1,
            'customer_name' => 'محمود ممدوح', 'customer_phone' => '01006262330',
            'visitor_id' => 'visitor-hidden-order',
            'recovered_order_id' => $order->id, 'recovered_at' => now(),
        ]);

        $this->actingAs($this->merchant)
            ->get('http://localhost/carts?filter=recovered')
            ->assertOk()
            ->assertDontSee('01006262330');
    }

    // ── What the merchant cannot do with it ──────────────────────────────

    public function test_a_hidden_order_cannot_be_worked_on(): void
    {
        $order = $this->hiddenOrder();
        $as = $this->actingAs($this->merchant);

        $as->patch("http://localhost/orders/{$order->id}/status", ['status' => Order::STATUS_CONFIRMED])->assertSessionHasErrors('order');
        $as->patch("http://localhost/orders/{$order->id}", ['address' => 'عنوان تاني'])->assertSessionHasErrors('order');
        $as->patch('http://localhost/orders-bulk/status', ['ids' => [$order->id], 'status' => Order::STATUS_CONFIRMED]);

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
        $this->assertSame('شارع الهرم، عمارة ٥', $order->fresh()->address);

        $as->post("http://localhost/orders/{$order->id}/whatsapp")->assertSessionHas('error');
    }

    public function test_no_paper_or_file_carries_a_hidden_customer(): void
    {
        $order = $this->hiddenOrder();

        $this->actingAs($this->merchant)
            ->get("http://localhost/orders/waybills?ids={$order->id}")
            ->assertDontSee('01006262330');

        // A streamed download: assertDontSee would read an empty body and
        // pass whatever the file held, so read what was actually streamed.
        $csv = $this->actingAs($this->merchant)->get('http://localhost/orders/export')->streamedContent();

        $this->assertStringContainsString('رقم الطلب', $csv);
        $this->assertStringNotContainsString('01006262330', $csv);
    }

    public function test_a_hidden_order_is_never_handed_to_a_carrier(): void
    {
        $order = $this->hiddenOrder();
        $order->forceFill(['status' => Order::STATUS_CONFIRMED])->save();

        $result = app(ShipOrdersViaDaman::class)->handle($this->store, [$order->id]);

        $this->assertSame(0, $result['sent'] ?? 0);
        $this->assertNull($order->fresh()->daman_shipment_id);
    }

    // ── Opening after a top-up ───────────────────────────────────────────

    public function test_a_confirmed_top_up_opens_hidden_orders_oldest_first_while_the_balance_allows(): void
    {
        Queue::fake();
        config(['services.whatsapp.waha.url' => 'http://waha.test', 'services.whatsapp.waha.api_key' => 'waha-key', 'services.whatsapp.waha.webhook_secret' => 'secret']);
        $this->store->whatsappIntegration()->create([
            'driver' => StoreWhatsappIntegration::DRIVER_WAHA,
            'credentials' => ['status' => 'WORKING'],
            'is_active' => true, 'auto_send' => true,
            'webhook_token' => StoreWhatsappIntegration::newToken(),
            'verify_token' => StoreWhatsappIntegration::newToken(),
            'connected_at' => now(),
        ]);

        $this->balance(-25);
        $this->order('01000000001');
        $this->order('01000000002');
        $this->order('01000000003');
        [$first, $second, $third] = Order::orderBy('id')->get()->all();
        $this->assertTrue($first->isLocked() && $second->isLocked() && $third->isLocked());

        // One pound: -25 → -24 opens the first (-24.75), still above the floor
        // so the second opens (-25.50), and that is past it — the third waits.
        $request = $this->store->topupRequests()->create(['amount' => 200, 'status' => StoreTopupRequest::STATUS_PENDING]);
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->post("http://localhost/admin/topups/{$request->id}/confirm", ['amount' => 1])
            ->assertSessionHasNoErrors();

        $this->assertFalse($first->fresh()->isLocked());
        $this->assertFalse($second->fresh()->isLocked());
        $this->assertTrue($third->fresh()->isLocked());

        $this->assertSame(2, StoreUsageEvent::where('type', StoreUsageEvent::TYPE_ORDER)->count());
        $this->assertSame('-25.50', (string) $this->store->refresh()->balance);

        Queue::assertPushed(SendWhatsappOrderConfirmation::class, 2);
        Queue::assertPushed(SendWhatsappOrderConfirmation::class, fn ($job) => $job->orderId === $first->id);
    }

    public function test_an_operators_manual_credit_opens_them_too(): void
    {
        $order = $this->hiddenOrder();

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->post("http://localhost/admin/stores/{$this->store->id}/wallet", [
                'direction' => 'credit', 'amount' => 100, 'note' => 'شحن يدوي',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($order->fresh()->isLocked());
        $this->assertSame('69.25', (string) $this->store->refresh()->balance);
    }

    public function test_an_opened_order_shows_its_customer_again(): void
    {
        $order = $this->hiddenOrder();
        app(WalletService::class)->credit($this->store, 100);
        app(\App\Services\Billing\OrderUnlocker::class)->unlock($this->store);

        $this->actingAs($this->merchant)
            ->get("http://localhost/orders/{$order->id}")
            ->assertInertia(fn ($page) => $page
                ->where('order.locked', false)
                ->where('order.customer_phone', '01006262330'));
    }
}

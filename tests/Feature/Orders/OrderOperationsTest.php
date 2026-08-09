<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderOperationsTest extends TestCase
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

    private function order(array $attributes = []): Order
    {
        $order = Order::factory()->for($this->store)->create($attributes);

        $order->items()->create([
            'name' => 'قميص قطن', 'variant_label' => 'أبيض · L',
            'unit_price' => 399, 'quantity' => 2, 'total' => 798,
        ]);

        return $order;
    }

    // ── Detail page ─────────────────────────────────────────────────────────

    public function test_the_operations_page_shows_everything_needed_to_dispatch(): void
    {
        $order = $this->order([
            'customer_name' => 'سارة عبد الله',
            'customer_phone' => '01223344556',
            'governorate' => 'الإسكندرية',
        ]);

        $this->actingAs($this->user)
            ->get('http://localhost/orders/' . $order->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('orders/Show')
                ->where('order.customer_name', 'سارة عبد الله')
                ->where('order.customer_phone', '01223344556')
                ->where('order.governorate', 'الإسكندرية')
                ->has('order.items', 1)
                ->where('order.items.0.variant_label', 'أبيض · L'));
    }

    public function test_a_merchant_cannot_open_another_stores_order(): void
    {
        $theirs = Order::factory()->for(Store::factory()->create())->create();

        $this->actingAs($this->user)
            ->get('http://localhost/orders/' . $theirs->id)
            ->assertForbidden();
    }

    // ── Customer history ────────────────────────────────────────────────────

    /** The number that decides whether a COD parcel is worth shipping. */
    public function test_the_page_counts_the_customers_past_behaviour(): void
    {
        $phone = '01006262330';

        $this->order(['customer_phone' => $phone, 'status' => Order::STATUS_DELIVERED]);
        $this->order(['customer_phone' => $phone, 'status' => Order::STATUS_DELIVERED]);
        $this->order(['customer_phone' => $phone, 'status' => Order::STATUS_RETURNED]);
        $current = $this->order(['customer_phone' => $phone]);

        $this->actingAs($this->user)
            ->get('http://localhost/orders/' . $current->id)
            ->assertInertia(fn ($page) => $page
                ->where('customer_history.orders', 3)
                ->where('customer_history.delivered', 2)
                ->where('customer_history.refused', 1)
                // 2 delivered of 3 settled.
                ->where('customer_history.delivery_rate', 67));
    }

    public function test_a_first_time_customer_has_no_delivery_rate(): void
    {
        $order = $this->order();

        $this->actingAs($this->user)
            ->get('http://localhost/orders/' . $order->id)
            ->assertInertia(fn ($page) => $page
                ->where('customer_history.orders', 0)
                // A rate out of zero is a number that misleads.
                ->where('customer_history.delivery_rate', null));
    }

    /** One merchant's customer history is not ours to hand to another. */
    public function test_history_never_leaks_across_stores(): void
    {
        $phone = '01006262330';

        $theirStore = Store::factory()->create();

        // Numbered explicitly: they are unique per store, and the factory hands
        // every row the same one.
        foreach (range(1, 3) as $number) {
            Order::factory()->for($theirStore)->create([
                'number' => $number,
                'customer_phone' => $phone,
                'status' => Order::STATUS_RETURNED,
            ]);
        }

        $mine = $this->order(['customer_phone' => $phone]);

        $this->actingAs($this->user)
            ->get('http://localhost/orders/' . $mine->id)
            ->assertInertia(fn ($page) => $page->where('customer_history.orders', 0));
    }

    // ── Waybills ────────────────────────────────────────────────────────────

    public function test_waybills_print_the_details_a_courier_needs(): void
    {
        $order = $this->order([
            'customer_name' => 'سارة عبد الله',
            'customer_phone' => '01223344556',
            'address' => 'سموحة، شارع فوزي معاذ',
            'total' => 798,
        ]);

        $this->actingAs($this->user)
            ->get('http://localhost/orders/waybills?ids=' . $order->id)
            ->assertOk()
            ->assertSee('سارة عبد الله')
            ->assertSee('01223344556')
            ->assertSee('سموحة، شارع فوزي معاذ')
            ->assertSee('798.00')
            ->assertSee('المطلوب تحصيله');
    }

    public function test_several_waybills_print_in_one_go(): void
    {
        $ids = collect(range(1, 3))->map(fn () => $this->order()->id);

        $this->actingAs($this->user)
            ->get('http://localhost/orders/waybills?ids=' . $ids->implode(','))
            ->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 3);
    }

    /** The ids ride in a URL anyone signed in could edit by hand. */
    public function test_waybills_never_print_another_stores_order(): void
    {
        $mine = $this->order();
        $theirs = Order::factory()->for(Store::factory()->create())->create(['customer_name' => 'مش بتاعه']);

        $this->actingAs($this->user)
            ->get("http://localhost/orders/waybills?ids={$mine->id},{$theirs->id}")
            ->assertOk()
            ->assertDontSee('مش بتاعه')
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 1);
    }

    // ── Export ──────────────────────────────────────────────────────────────

    public function test_the_export_is_a_utf8_csv_excel_can_open(): void
    {
        $this->order(['customer_name' => 'سارة عبد الله']);

        $response = $this->actingAs($this->user)->get('http://localhost/orders/export');
        $response->assertOk();

        $csv = $response->streamedContent();

        // Without the BOM, Excel on Windows reads this as the system codepage
        // and every Arabic name arrives as mojibake.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('سارة عبد الله', $csv);
        $this->assertStringContainsString('رقم الطلب', $csv);
    }

    public function test_the_export_respects_the_current_filter(): void
    {
        $this->order(['customer_name' => 'مراجعة', 'status' => Order::STATUS_PENDING]);
        $this->order(['customer_name' => 'اتسلّم', 'status' => Order::STATUS_DELIVERED]);

        $csv = $this->actingAs($this->user)
            ->get('http://localhost/orders/export?status=delivered')
            ->streamedContent();

        $this->assertStringContainsString('اتسلّم', $csv);
        $this->assertStringNotContainsString('مراجعة', $csv);
    }

    public function test_the_export_only_contains_the_merchants_own_orders(): void
    {
        $this->order(['customer_name' => 'بتاعي']);
        Order::factory()->for(Store::factory()->create())->create(['customer_name' => 'مش بتاعي']);

        $csv = $this->actingAs($this->user)
            ->get('http://localhost/orders/export')
            ->streamedContent();

        $this->assertStringContainsString('بتاعي', $csv);
        $this->assertStringNotContainsString('مش بتاعي', $csv);
    }
}

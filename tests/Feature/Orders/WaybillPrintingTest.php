<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Printing waybills: by selection, or as the day's two print runs — today's
 * orders, and whatever has not been printed yet — in three paper sizes, each
 * label carrying a QR code back to its order.
 */
class WaybillPrintingTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = User::factory()->create();
        $this->store = Store::factory()->for($this->merchant)->create();
    }

    /** @param array<string,mixed> $attributes */
    private function order(array $attributes = []): Order
    {
        $order = Order::factory()->for($this->store)->create([
            'status' => Order::STATUS_CONFIRMED,
            'customer_phone' => '0100' . random_int(1000000, 9999999),
            ...$attributes,
        ]);
        $order->items()->create(['name' => 'قميص قطن', 'unit_price' => 250, 'quantity' => 1, 'total' => 250]);

        return $order;
    }

    private function print(string $query): TestResponse
    {
        return $this->actingAs($this->merchant)->get('http://localhost/orders/waybills?' . $query);
    }

    // ── The label ────────────────────────────────────────────────────────

    public function test_each_label_carries_a_qr_code_to_its_order(): void
    {
        $order = $this->order();

        $html = $this->print("ids={$order->id}")->assertOk()->assertSee($order->customer_phone)->getContent();

        $this->assertSame(1, substr_count($html, '<svg'));
        $this->assertStringContainsString('امسح وافتح الطلب', $html);
    }

    public function test_the_paper_follows_the_chosen_size(): void
    {
        $order = $this->order();

        $this->print("ids={$order->id}&size=label")->assertSee('size: 100mm 150mm', escape: false);
        $this->print("ids={$order->id}&size=a4")->assertSee('size: A4', escape: false);
        $this->print("ids={$order->id}&size=nonsense")->assertSee('size: A5', escape: false);
    }

    // ── What gets printed ────────────────────────────────────────────────

    public function test_opening_a_waybill_marks_the_order_printed(): void
    {
        $order = $this->order();

        $this->print("ids={$order->id}");

        $this->assertNotNull($order->fresh()->waybill_printed_at);
    }

    public function test_unprinted_prints_each_order_once(): void
    {
        $done = $this->order(['waybill_printed_at' => now()->subDay()]);
        $new = $this->order();
        $cancelled = $this->order(['status' => Order::STATUS_CANCELLED]);

        $this->print('scope=unprinted')
            ->assertOk()
            ->assertSee($new->customer_phone)
            ->assertDontSee($done->customer_phone)
            ->assertDontSee($cancelled->customer_phone);

        // The same button again: nothing left to print.
        $this->print('scope=unprinted')->assertOk()->assertSee('مفيش طلبات تطبعلها بوالص هنا');
    }

    public function test_today_prints_only_todays_orders_still_to_go_out(): void
    {
        $today = $this->order();
        $yesterday = $this->order(['created_at' => now()->subDay()]);
        $shipped = $this->order(['status' => Order::STATUS_SHIPPED]);

        $this->print('scope=today')
            ->assertSee($today->customer_phone)
            ->assertDontSee($yesterday->customer_phone)
            ->assertDontSee($shipped->customer_phone);
    }

    public function test_a_hidden_order_is_never_printed_however_it_is_asked_for(): void
    {
        $hidden = $this->order(['locked_at' => now()]);

        $this->print('scope=unprinted')->assertDontSee($hidden->customer_phone);
        $this->print('scope=today')->assertDontSee($hidden->customer_phone);
        $this->print("ids={$hidden->id}")->assertDontSee($hidden->customer_phone);

        $this->assertNull($hidden->fresh()->waybill_printed_at);
    }

    public function test_another_stores_orders_are_never_printed(): void
    {
        $theirs = Order::factory()->for(Store::factory()->create())->create([
            'status' => Order::STATUS_CONFIRMED, 'customer_phone' => '01112223334',
        ]);

        $this->print("ids={$theirs->id}")->assertDontSee('01112223334');
        $this->print('scope=unprinted')->assertDontSee('01112223334');

        $this->assertNull($theirs->fresh()->waybill_printed_at);
    }

    // ── The buttons ──────────────────────────────────────────────────────

    public function test_the_orders_page_counts_what_each_button_would_print(): void
    {
        $this->order(['waybill_printed_at' => now()]);
        $this->order();
        $this->order(['created_at' => now()->subDays(2)]);

        $this->actingAs($this->merchant)
            ->get('http://localhost/orders')
            ->assertInertia(fn ($page) => $page
                ->where('printable.today', 2)
                ->where('printable.unprinted', 2));
    }
}

<?php

namespace Tests\Feature\Customers;

use App\Models\CustomerReputation;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The platform-wide delivery record.
 *
 * This is the one signal a store-builder cannot produce, and it accuses real
 * customers — so the tests are about not being wrong: no double counting when
 * a merchant corrects a status, no warning on thin evidence, and nothing about
 * one merchant's shop reaching another's screen.
 */
class CustomerReputationTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '01006262330';

    private function order(Store $store, int $number, array $attributes = []): Order
    {
        return Order::factory()->for($store)->create([
            'number' => $number,
            'customer_phone' => self::PHONE,
            ...$attributes,
        ]);
    }

    private function record(): ?CustomerReputation
    {
        return CustomerReputation::where('phone', self::PHONE)->first();
    }

    // ── Recording ───────────────────────────────────────────────────────────

    public function test_a_delivered_order_is_counted(): void
    {
        $this->order(Store::factory()->create(), 1, ['status' => Order::STATUS_DELIVERED]);

        $this->assertSame(1, $this->record()->delivered);
        $this->assertSame(100, $this->record()->deliveryRate());
    }

    public function test_a_returned_order_counts_as_a_refusal(): void
    {
        $this->order(Store::factory()->create(), 1, ['status' => Order::STATUS_RETURNED]);

        $this->assertSame(1, $this->record()->refused);
    }

    /**
     * A cancellation before dispatch is usually the shop's own decision, and a
     * pending order says nothing at all. Blaming the buyer for either would
     * poison the signal.
     */
    public function test_orders_that_never_shipped_contribute_nothing(): void
    {
        $store = Store::factory()->create();

        $this->order($store, 1, ['status' => Order::STATUS_PENDING]);
        $this->order($store, 2, ['status' => Order::STATUS_CONFIRMED]);

        $this->assertSame(0, $this->record()->settled());
    }

    /**
     * The failure mode that would make this feature lie: merchants correct
     * statuses constantly, and a counter that only goes up turns one parcel
     * into two and eventually accuses a good customer.
     */
    public function test_correcting_a_status_moves_the_count_instead_of_adding(): void
    {
        $order = $this->order(Store::factory()->create(), 1, ['status' => Order::STATUS_DELIVERED]);

        $this->assertSame(1, $this->record()->delivered);

        $order->update(['status' => Order::STATUS_RETURNED]);

        $this->assertSame(0, $this->record()->delivered);
        $this->assertSame(1, $this->record()->refused);
    }

    public function test_rolling_an_order_back_before_dispatch_drops_its_contribution(): void
    {
        $order = $this->order(Store::factory()->create(), 1, ['status' => Order::STATUS_DELIVERED]);

        $order->update(['status' => Order::STATUS_CONFIRMED]);

        $this->assertSame(0, $this->record()->settled());
    }

    /** Editing an address must not rewrite the customer's history. */
    public function test_an_unrelated_edit_leaves_the_record_alone(): void
    {
        $order = $this->order(Store::factory()->create(), 1, ['status' => Order::STATUS_DELIVERED]);

        $order->update(['address' => 'عنوان جديد']);

        $this->assertSame(1, $this->record()->delivered);
    }

    /** One shop's three refusals is a dispute; three shops' is a pattern. */
    public function test_distinct_stores_are_counted(): void
    {
        foreach (range(1, 3) as $i) {
            $this->order(Store::factory()->create(), 1, ['status' => Order::STATUS_RETURNED]);
        }

        $this->assertSame(3, $this->record()->refused);
        $this->assertSame(3, $this->record()->stores_count);
    }

    public function test_one_store_shipping_twice_counts_as_one_store(): void
    {
        $store = Store::factory()->create();

        $this->order($store, 1, ['status' => Order::STATUS_RETURNED]);
        $this->order($store, 2, ['status' => Order::STATUS_RETURNED]);

        $this->assertSame(2, $this->record()->refused);
        $this->assertSame(1, $this->record()->stores_count);
    }

    // ── The warning threshold ───────────────────────────────────────────────

    /**
     * One refusal is as often a wrong address or a courier who never called as
     * it is a bad customer. A warning that fires on noise is one merchants
     * learn to click past.
     */
    public function test_a_single_refusal_is_not_a_warning(): void
    {
        $this->order(Store::factory()->create(), 1, ['status' => Order::STATUS_RETURNED]);

        $this->assertFalse($this->record()->isRisky());
    }

    public function test_two_refusals_with_a_poor_rate_is_a_warning(): void
    {
        foreach (range(1, 2) as $i) {
            $this->order(Store::factory()->create(), 1, ['status' => Order::STATUS_RETURNED]);
        }

        $this->assertTrue($this->record()->isRisky());
        $this->assertStringContainsString('رفض', $this->record()->summary());
    }

    /** Two refusals out of thirty is a normal customer, not a risk. */
    public function test_refusals_against_a_long_good_history_are_not_a_warning(): void
    {
        $store = Store::factory()->create();
        $n = 1;

        foreach (range(1, 28) as $i) {
            $this->order($store, $n++, ['status' => Order::STATUS_DELIVERED]);
        }
        foreach (range(1, 2) as $i) {
            $this->order($store, $n++, ['status' => Order::STATUS_RETURNED]);
        }

        $this->assertFalse($this->record()->isRisky());
    }

    public function test_a_reliable_customer_gets_a_positive_summary(): void
    {
        $store = Store::factory()->create();

        foreach (range(1, 4) as $i) {
            $this->order($store, $i, ['status' => Order::STATUS_DELIVERED]);
        }

        $this->assertStringContainsString('موثوق', $this->record()->summary());
    }

    /** A first-time buyer is not a 0% customer. */
    public function test_an_unknown_number_has_no_rate_and_no_summary(): void
    {
        $record = CustomerReputation::create(['phone' => '01111111111']);

        $this->assertNull($record->deliveryRate());
        $this->assertNull($record->summary());
    }

    // ── What the merchant sees ──────────────────────────────────────────────

    public function test_the_order_page_shows_the_network_record(): void
    {
        $user = User::factory()->create();
        $mine = Store::factory()->for($user)->create();

        // Refused twice at other shops, brand new at this one.
        foreach (range(1, 2) as $i) {
            $this->order(Store::factory()->create(), 1, ['status' => Order::STATUS_RETURNED]);
        }

        $order = $this->order($mine, 1);

        $this->actingAs($user)
            ->get('http://localhost/orders/' . $order->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('network_reputation.refused', 2)
                ->where('network_reputation.stores', 2)
                ->where('network_reputation.risky', true)
                // This shop has shipped nothing to them yet — which is exactly
                // why the network view is the one worth showing.
                ->where('customer_history.orders', 0));
    }

    /**
     * A warning about a phone number is useful. A window into which shops it
     * bought from is a competitor's customer list, and not ours to open.
     */
    public function test_the_payload_never_names_another_store(): void
    {
        $user = User::factory()->create();
        $mine = Store::factory()->for($user)->create();

        $theirs = Store::factory()->create(['name' => 'متجر المنافس']);
        $this->order($theirs, 1, ['status' => Order::STATUS_DELIVERED]);

        $order = $this->order($mine, 1);

        $response = $this->actingAs($user)->get('http://localhost/orders/' . $order->id)->assertOk();

        $response->assertDontSee('متجر المنافس');

        $response->assertInertia(fn ($page) => $page
            ->missing('network_reputation.orders')
            ->missing('network_reputation.entries')
            ->where('network_reputation.delivered', 1));
    }

    public function test_a_number_with_no_shipping_history_shows_no_panel(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->for($user)->create();

        $order = $this->order($store, 1);

        $this->actingAs($user)
            ->get('http://localhost/orders/' . $order->id)
            ->assertOk()
            // "No data" on screen is noise; the panel simply does not render.
            ->assertInertia(fn ($page) => $page->where('network_reputation', null));
    }
}

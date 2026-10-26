<?php

namespace Tests\Feature\Shipping;

use App\Models\CarrierCollection;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Shipping\CarrierLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * الشحن والتحصيل: what each carrier holds, and recording when they pay.
 */
class CarrierCollectionsTest extends TestCase
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

    private function order(string $status, float $total, ?string $carrier): Order
    {
        $order = Order::factory()->for($this->store)->create(['status' => $status, 'subtotal' => $total, 'total' => $total]);
        $order->forceFill(['daman_carrier_name' => $carrier])->save();

        return $order;
    }

    /** @return array<string,mixed> */
    private function carrier(string $key): array
    {
        return collect(app(CarrierLedger::class)->carriers($this->store))->firstWhere('key', $key);
    }

    public function test_each_carrier_shows_its_road_its_debt_and_its_returns(): void
    {
        $this->order(Order::STATUS_SHIPPED, 300, 'Bosta');
        $this->order(Order::STATUS_DELIVERED, 500, 'Bosta');
        $this->order(Order::STATUS_DELIVERED, 250, 'Bosta');
        $this->order(Order::STATUS_RETURNED, 400, 'Bosta');
        $this->order(Order::STATUS_DELIVERED, 900, 'Aramex');
        // Not shipments: never counted.
        $this->order(Order::STATUS_CONFIRMED, 1000, null);
        $this->order(Order::STATUS_CANCELLED, 1000, 'Bosta');

        $bosta = $this->carrier('Bosta');

        $this->assertSame(1, $bosta['in_transit_count']);
        $this->assertSame(300.0, $bosta['in_transit_value']);
        $this->assertSame(2, $bosta['due_count']);
        $this->assertSame(750.0, $bosta['due_value']);
        $this->assertSame(1, $bosta['returned_count']);
        $this->assertSame(67, $bosta['delivery_rate'], '2 delivered of 3 that reached an end');
        $this->assertSame(900.0, $this->carrier('Aramex')['due_value']);
    }

    public function test_the_overall_delivery_rate_and_the_parcels_running_late(): void
    {
        $this->order(Order::STATUS_DELIVERED, 100, 'Bosta');
        $this->order(Order::STATUS_DELIVERED, 100, 'Bosta');
        $this->order(Order::STATUS_DELIVERED, 100, 'Aramex');
        $this->order(Order::STATUS_RETURNED, 100, 'Aramex');

        // On the road: one handed over a week ago, one this morning.
        $late = $this->order(Order::STATUS_SHIPPED, 100, 'Bosta');
        $late->forceFill(['daman_sent_at' => now()->subDays(7)])->save();
        $fresh = $this->order(Order::STATUS_SHIPPED, 100, 'Bosta');
        $fresh->forceFill(['daman_sent_at' => now()->subHours(3)])->save();

        $ledger = app(CarrierLedger::class);
        $totals = $ledger->totals($ledger->carriers($this->store));

        $this->assertSame(6, $totals['sent_count']);
        $this->assertSame(75, $totals['delivery_rate'], '3 delivered of 4 that reached an end — not of all 6 sent');
        $this->assertSame(1, $totals['late_count']);

        $this->assertSame(100, $this->carrier('Bosta')['delivery_rate']);
        $this->assertSame(50, $this->carrier('Aramex')['delivery_rate']);
        $this->assertSame(1, $this->carrier('Bosta')['late_count']);
        $this->assertSame(4, $this->carrier('Bosta')['sent_count']);
    }

    public function test_no_rate_is_claimed_before_any_parcel_reached_an_end(): void
    {
        $this->order(Order::STATUS_SHIPPED, 100, 'Bosta');

        $ledger = app(CarrierLedger::class);

        $this->assertNull($ledger->totals($ledger->carriers($this->store))['delivery_rate']);
        $this->assertNull($this->carrier('Bosta')['delivery_rate']);
    }

    public function test_orders_shipped_without_daman_are_grouped_as_the_merchants_own(): void
    {
        $this->order(Order::STATUS_DELIVERED, 200, null);

        $own = $this->carrier(CarrierLedger::MANUAL);

        $this->assertNull($own['name']);
        $this->assertSame(200.0, $own['due_value']);
    }

    public function test_recording_a_collection_settles_those_orders_and_keeps_the_gap(): void
    {
        $a = $this->order(Order::STATUS_DELIVERED, 500, 'Bosta');
        $b = $this->order(Order::STATUS_DELIVERED, 250, 'Bosta');
        $later = $this->order(Order::STATUS_DELIVERED, 100, 'Bosta');

        $this->actingAs($this->merchant)->post('/shipments/collections', [
            'carrier' => 'Bosta',
            'order_ids' => [$a->id, $b->id],
            'amount' => 700,
            'received_on' => now()->toDateString(),
            'reference' => 'IP-1234',
        ])->assertRedirect()->assertSessionHas('status', 'collection-recorded');

        $collection = CarrierCollection::sole();
        $this->assertSame('750.00', $collection->expected_amount);
        $this->assertSame(50.0, $collection->difference(), 'the carrier kept 50');
        $this->assertSame(2, $collection->orders_count);

        $bosta = $this->carrier('Bosta');
        $this->assertSame(1, $bosta['due_count']);
        $this->assertSame(100.0, $bosta['due_value'], 'only the order not paid for yet');
        $this->assertSame(700.0, $bosta['collected_value']);
        $this->assertNull($later->fresh()->carrier_collection_id);
    }

    public function test_an_order_cannot_be_collected_twice_or_from_the_wrong_carrier(): void
    {
        $bosta = $this->order(Order::STATUS_DELIVERED, 500, 'Bosta');
        $aramex = $this->order(Order::STATUS_DELIVERED, 300, 'Aramex');
        $onTheRoad = $this->order(Order::STATUS_SHIPPED, 200, 'Bosta');

        $post = fn (array $ids) => $this->actingAs($this->merchant)->post('/shipments/collections', [
            'carrier' => 'Bosta', 'order_ids' => $ids, 'amount' => 500, 'received_on' => now()->toDateString(),
        ]);

        $post([$aramex->id])->assertSessionHasErrors('order_ids');
        $post([$onTheRoad->id])->assertSessionHasErrors('order_ids');
        $post([$bosta->id])->assertSessionHasNoErrors();
        $post([$bosta->id])->assertSessionHasErrors('order_ids');

        $this->assertSame(1, CarrierCollection::count());
    }

    public function test_another_stores_orders_and_collections_are_out_of_reach(): void
    {
        $theirs = Order::factory()->create(['status' => Order::STATUS_DELIVERED, 'total' => 999]);
        $theirs->forceFill(['daman_carrier_name' => 'Bosta'])->save();

        $this->actingAs($this->merchant)->post('/shipments/collections', [
            'carrier' => 'Bosta', 'order_ids' => [$theirs->id], 'amount' => 999, 'received_on' => now()->toDateString(),
        ])->assertSessionHasErrors('order_ids');

        $otherCollection = CarrierCollection::create([
            'store_id' => $theirs->store_id, 'carrier' => 'Bosta', 'amount' => 1, 'expected_amount' => 1,
            'orders_count' => 1, 'received_on' => now()->toDateString(),
        ]);
        $this->actingAs($this->merchant)->delete("/shipments/collections/{$otherCollection->id}")->assertNotFound();
    }

    public function test_undoing_a_collection_puts_its_orders_back_as_owed(): void
    {
        $order = $this->order(Order::STATUS_DELIVERED, 500, 'Bosta');
        $collection = app(CarrierLedger::class)->record($this->store, $this->merchant, 'Bosta', [$order->id], 500, now()->toDateString());

        $this->actingAs($this->merchant)->delete("/shipments/collections/{$collection->id}")->assertRedirect();

        $this->assertNull($order->fresh()->carrier_collection_id);
        $this->assertSame(0, CarrierCollection::count());
        $this->assertSame(500.0, $this->carrier('Bosta')['due_value']);
    }

    public function test_the_due_list_and_the_page_and_the_dashboard_card(): void
    {
        $this->order(Order::STATUS_DELIVERED, 500, 'Bosta');
        $this->order(Order::STATUS_SHIPPED, 300, 'Bosta');

        $this->actingAs($this->merchant)->getJson('/shipments/due?carrier=Bosta')
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.total', 500);

        $this->actingAs($this->merchant)->get('/shipments')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('shipments/Index')
                ->where('totals.due_value', 500)
                ->where('totals.in_transit_value', 300)
                ->has('carriers', 1));

        $this->actingAs($this->merchant)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('shipping.due_value', 500));
    }
}

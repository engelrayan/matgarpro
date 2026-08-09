<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderGridTest extends TestCase
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
        return Order::factory()->for($this->store)->create($attributes);
    }

    // ── Inline editing ──────────────────────────────────────────────────────

    public function test_a_merchant_can_edit_one_field_at_a_time(): void
    {
        $order = $this->order(['customer_name' => 'محمود', 'address' => 'القاهرة']);

        $this->actingAs($this->user)
            ->patch('http://localhost/orders/' . $order->id, ['customer_name' => 'محمود ممدوح'])
            ->assertSessionHasNoErrors();

        $order->refresh();

        $this->assertSame('محمود ممدوح', $order->customer_name);
        // Fields not in the payload must be left alone — the grid sends one
        // cell, not the whole row.
        $this->assertSame('القاهرة', $order->address);
    }

    /** A hand-typed phone deserves the same normalisation as a customer's. */
    public function test_a_typed_phone_is_normalised(): void
    {
        $order = $this->order();

        $this->actingAs($this->user)->patch('http://localhost/orders/' . $order->id, [
            'customer_phone' => '٠١٠ ٠٦٢٦ ٢٣٣٠',
        ]);

        $this->assertSame('01006262330', $order->fresh()->customer_phone);
    }

    public function test_the_name_and_phone_cannot_be_emptied(): void
    {
        $order = $this->order(['customer_name' => 'محمود']);

        $this->actingAs($this->user)
            ->patch('http://localhost/orders/' . $order->id, ['customer_name' => ''])
            ->assertSessionHasErrors('customer_name');

        $this->assertSame('محمود', $order->fresh()->customer_name);
    }

    /**
     * Money and stock are not editable from a click-and-type grid — that is
     * the wrong safety level for either.
     */
    public function test_totals_cannot_be_edited_from_the_grid(): void
    {
        $order = $this->order(['total' => 500, 'subtotal' => 500]);

        $this->actingAs($this->user)->patch('http://localhost/orders/' . $order->id, [
            'total' => 1,
            'subtotal' => 1,
            'customer_name' => 'محمود',
        ]);

        $order->refresh();

        $this->assertSame('500.00', $order->total);
        $this->assertSame('500.00', $order->subtotal);
    }

    public function test_status_can_be_changed_inline(): void
    {
        $order = $this->order(['status' => Order::STATUS_PENDING]);

        $this->actingAs($this->user)
            ->patch('http://localhost/orders/' . $order->id, ['status' => Order::STATUS_CONFIRMED])
            ->assertSessionHasNoErrors();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $order = $this->order(['status' => Order::STATUS_PENDING]);

        $this->actingAs($this->user)
            ->patch('http://localhost/orders/' . $order->id, ['status' => 'teleported'])
            ->assertSessionHasErrors('status');
    }

    public function test_a_merchant_cannot_edit_another_stores_order(): void
    {
        $theirs = Order::factory()->for(Store::factory()->create())->create(['customer_name' => 'مش بتاعه']);

        $this->actingAs($this->user)
            ->patch('http://localhost/orders/' . $theirs->id, ['customer_name' => 'مخترق'])
            ->assertForbidden();

        $this->assertSame('مش بتاعه', $theirs->fresh()->customer_name);
    }

    // ── Bulk ────────────────────────────────────────────────────────────────

    public function test_a_merchant_can_move_many_orders_at_once(): void
    {
        $ids = collect(range(1, 3))->map(fn () => $this->order()->id)->all();

        $this->actingAs($this->user)
            ->patch('http://localhost/orders-bulk/status', ['ids' => $ids, 'status' => Order::STATUS_CONFIRMED])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $this->store->orders()->where('status', Order::STATUS_CONFIRMED)->count());
    }

    /** A crafted id list must not reach another store's orders. */
    public function test_bulk_updates_are_scoped_to_the_merchants_store(): void
    {
        $mine = $this->order();
        $theirs = Order::factory()->for(Store::factory()->create())->create(['status' => Order::STATUS_PENDING]);

        $this->actingAs($this->user)->patch('http://localhost/orders-bulk/status', [
            'ids' => [$mine->id, $theirs->id],
            'status' => Order::STATUS_DELIVERED,
        ]);

        $this->assertSame(Order::STATUS_DELIVERED, $mine->fresh()->status);
        $this->assertSame(Order::STATUS_PENDING, $theirs->fresh()->status);
    }

    // ── Filtering & sorting ─────────────────────────────────────────────────

    public function test_orders_can_be_filtered_by_status_and_governorate(): void
    {
        $this->order(['status' => Order::STATUS_PENDING, 'governorate' => 'القاهرة']);
        $this->order(['status' => Order::STATUS_DELIVERED, 'governorate' => 'القاهرة']);
        $this->order(['status' => Order::STATUS_PENDING, 'governorate' => 'الجيزة']);

        $this->actingAs($this->user)
            ->get('http://localhost/orders?status=pending&gov=' . urlencode('القاهرة'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('orders.data', 1));
    }

    public function test_search_matches_name_phone_and_order_number(): void
    {
        $this->order(['customer_name' => 'محمود ممدوح', 'customer_phone' => '01006262330']);
        $this->order(['customer_name' => 'أحمد', 'customer_phone' => '01111111111']);

        foreach (['محمود', '01006262330'] as $term) {
            $this->actingAs($this->user)
                ->get('http://localhost/orders?q=' . urlencode($term))
                ->assertInertia(fn ($page) => $page->has('orders.data', 1));
        }
    }

    /** A crafted `sort` must not be able to order by an arbitrary column. */
    public function test_an_unknown_sort_column_falls_back(): void
    {
        $this->order();

        $this->actingAs($this->user)
            ->get('http://localhost/orders?sort=password&dir=asc')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('filters.sort', 'id'));
    }

    public function test_sorting_by_total_works_in_both_directions(): void
    {
        $this->order(['total' => 100, 'subtotal' => 100]);
        $this->order(['total' => 900, 'subtotal' => 900]);

        $this->actingAs($this->user)
            ->get('http://localhost/orders?sort=total&dir=asc')
            ->assertInertia(fn ($page) => $page->where('orders.data.0.total', '100.00'));

        $this->actingAs($this->user)
            ->get('http://localhost/orders?sort=total&dir=desc')
            ->assertInertia(fn ($page) => $page->where('orders.data.0.total', '900.00'));
    }

    /** The filter only lists governorates this store actually ships to. */
    public function test_the_governorate_filter_lists_only_used_values(): void
    {
        $this->order(['governorate' => 'القاهرة']);
        $this->order(['governorate' => 'القاهرة']);
        $this->order(['governorate' => null]);

        $this->actingAs($this->user)
            ->get('http://localhost/orders')
            ->assertInertia(fn ($page) => $page->has('governorates', 1));
    }
}

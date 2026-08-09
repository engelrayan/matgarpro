<?php

namespace Tests\Feature\Storefront;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreUsageEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function store(): Store
    {
        return Store::factory()->create(['slug' => 'mahmoud', 'status' => Store::STATUS_ACTIVE]);
    }

    private function host(Store $store): string
    {
        return 'http://' . $store->platformHost();
    }

    /** @return array<string,mixed> */
    private function customer(array $overrides = []): array
    {
        return array_merge([
            'customer_name' => 'محمود ممدوح',
            'customer_phone' => '01006262330',
            'governorate' => 'القاهرة',
            'address' => 'شارع الهرم، عمارة ٥، الدور ٣',
            'quantity' => 1,
        ], $overrides);
    }

    public function test_a_customer_can_place_a_cash_on_delivery_order(): void
    {
        $store = $this->store();
        $product = Product::factory()->for($store)->create(['price' => 300, 'stock' => 10]);

        $this->post($this->host($store) . '/checkout', $this->customer([
            'product_id' => $product->id,
            'quantity' => 2,
        ]))->assertRedirect();

        $order = Order::firstOrFail();

        $this->assertSame(1, $order->number, 'the first order in a store is #1');
        $this->assertSame('600.00', $order->total);
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(8, $product->fresh()->stock, 'stock is drawn down');
        $this->assertSame('600.00', $order->items->first()->total);
    }

    public function test_the_posted_price_is_ignored(): void
    {
        $store = $this->store();
        $product = Product::factory()->for($store)->create(['price' => 300]);

        // A tampered form offering to pay 1 pound.
        $this->post($this->host($store) . '/checkout', $this->customer([
            'product_id' => $product->id,
            'price' => 1,
            'unit_price' => 1,
            'total' => 1,
        ]))->assertRedirect();

        $this->assertSame('300.00', Order::firstOrFail()->total);
    }

    public function test_an_order_cannot_exceed_stock(): void
    {
        $store = $this->store();
        $product = Product::factory()->for($store)->create(['stock' => 2]);

        $this->from($this->host($store) . '/p/' . $product->slug)
            ->post($this->host($store) . '/checkout', $this->customer([
                'product_id' => $product->id,
                'quantity' => 5,
            ]))
            ->assertSessionHasErrors('checkout');

        $this->assertSame(0, Order::count());
        $this->assertSame(2, $product->fresh()->stock, 'a rejected order leaves stock alone');
    }

    public function test_a_variant_product_requires_a_variant(): void
    {
        $store = $this->store();
        $product = Product::factory()->for($store)->withVariants()->create();

        $this->from($this->host($store) . '/p/' . $product->slug)
            ->post($this->host($store) . '/checkout', $this->customer([
                'product_id' => $product->id,
            ]))
            ->assertSessionHasErrors('checkout');

        $this->assertSame(0, Order::count());
    }

    public function test_a_variant_order_records_what_the_customer_picked(): void
    {
        $store = $this->store();
        $product = Product::factory()->for($store)->withVariants()->create(['price' => 200]);
        $variant = $product->variants()->first();
        $variant->update(['price' => 250]);

        $this->post($this->host($store) . '/checkout', $this->customer([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
        ]))->assertRedirect();

        $item = Order::firstOrFail()->items->first();

        $this->assertSame('250.00', $item->unit_price, 'the variant price beats the product price');
        $this->assertSame('أحمر · M', $item->variant_label);
        $this->assertSame(4, $variant->fresh()->stock);
    }

    /**
     * The common case: a variant with no price of its own falls back to the
     * product's. It must not reach for the product relation to find it.
     */
    public function test_a_variant_without_its_own_price_uses_the_product_price(): void
    {
        $store = $this->store();
        $product = Product::factory()->for($store)->withVariants()->create(['price' => 200]);
        $variant = $product->variants()->first();

        $this->assertNull($variant->price, 'the factory leaves variant prices unset');

        $this->post($this->host($store) . '/checkout', $this->customer([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
        ]))->assertRedirect();

        $this->assertSame('200.00', Order::firstOrFail()->items->first()->unit_price);
    }

    public function test_a_variant_product_page_renders_every_combination(): void
    {
        $store = $this->store();
        $product = Product::factory()->for($store)->withVariants()->create();

        $this->get($this->host($store) . '/p/' . $product->slug)
            ->assertOk()
            ->assertSee('اللون')
            ->assertSee('المقاس')
            ->assertSee('أحمر');
    }

    public function test_a_draft_product_cannot_be_ordered(): void
    {
        $store = $this->store();
        $product = Product::factory()->for($store)->draft()->create();

        $this->from($this->host($store) . '/p/' . $product->slug)
            ->post($this->host($store) . '/checkout', $this->customer(['product_id' => $product->id]))
            ->assertSessionHasErrors('checkout');
    }

    public function test_a_product_from_another_store_cannot_be_ordered(): void
    {
        $store = $this->store();
        $other = Product::factory()->create();

        $this->from($this->host($store))
            ->post($this->host($store) . '/checkout', $this->customer(['product_id' => $other->id]))
            ->assertSessionHasErrors('checkout');

        $this->assertSame(0, Order::count());
    }

    public function test_placing_an_order_records_a_usage_event(): void
    {
        $store = $this->store();
        $product = Product::factory()->for($store)->create();

        $this->post($this->host($store) . '/checkout', $this->customer(['product_id' => $product->id]));

        $event = StoreUsageEvent::firstOrFail();

        $this->assertSame($store->id, $event->store_id);
        // Free plan: the event exists, the charge is zero.
        $this->assertSame('0.00', $event->amount);
    }

    /**
     * Phone numbers arrive in whatever shape the customer's keyboard produced.
     * Two spellings of one number must not become two customers.
     */
    public function test_phone_numbers_are_normalised(): void
    {
        $store = $this->store();
        $product = Product::factory()->for($store)->create();

        foreach (['+20 100 626 2330', '٠١٠٠٦٢٦٢٣٣٠', '0100 626 2330'] as $typed) {
            $this->post($this->host($store) . '/checkout', $this->customer([
                'product_id' => $product->id,
                'customer_phone' => $typed,
            ]))->assertRedirect();
        }

        $this->assertSame(
            ['01006262330'],
            Order::pluck('customer_phone')->unique()->values()->all(),
        );
    }

    /**
     * A cash-on-delivery order with no reachable number is worthless — the
     * merchant pays to ship a parcel they can never confirm.
     */
    public function test_a_phone_that_normalises_to_nothing_is_rejected(): void
    {
        $store = $this->store();
        $product = Product::factory()->for($store)->create();

        foreach (['---', 'اتصل بيا', '123'] as $unusable) {
            $this->from($this->host($store) . '/p/' . $product->slug)
                ->post($this->host($store) . '/checkout', $this->customer([
                    'product_id' => $product->id,
                    'customer_phone' => $unusable,
                ]))
                ->assertSessionHasErrors('customer_phone');
        }

        $this->assertSame(0, Order::count());
    }

    public function test_order_numbers_are_sequential_per_store(): void
    {
        $storeA = $this->store();
        $storeB = Store::factory()->create(['slug' => 'ahmed']);

        $productA = Product::factory()->for($storeA)->create();
        $productB = Product::factory()->for($storeB)->create();

        $this->post($this->host($storeA) . '/checkout', $this->customer(['product_id' => $productA->id]));
        $this->post($this->host($storeA) . '/checkout', $this->customer(['product_id' => $productA->id]));
        $this->post($this->host($storeB) . '/checkout', $this->customer(['product_id' => $productB->id]));

        $this->assertSame([1, 2], $storeA->orders()->orderBy('number')->pluck('number')->all());
        $this->assertSame([1], $storeB->orders()->pluck('number')->all(), 'each store counts from 1');
    }

    public function test_the_product_page_renders_for_a_customer(): void
    {
        $store = $this->store();
        $product = Product::factory()->for($store)->create(['name' => 'قميص قطن', 'price' => 399]);

        $this->get($this->host($store) . '/p/' . $product->slug)
            ->assertOk()
            ->assertSee('قميص قطن')
            ->assertSee('399.00')
            ->assertSee('الدفع عند الاستلام');
    }

    public function test_a_draft_product_page_is_not_reachable(): void
    {
        $store = $this->store();
        $product = Product::factory()->for($store)->draft()->create();

        $this->get($this->host($store) . '/p/' . $product->slug)->assertNotFound();
    }
}

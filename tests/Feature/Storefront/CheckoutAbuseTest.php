<?php

namespace Tests\Feature\Storefront;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Checkout is the one public endpoint that costs the merchant money.
 *
 * `PlaceOrder` bills the store the instant an order is created, so an
 * unprotected checkout is not a spam problem — it is a way to drain a
 * competitor's balance past the overdraft floor until their shop stops
 * accepting real orders. Every test here is about that.
 */
class CheckoutAbuseTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('checkout');

        $this->store = Store::factory()->create(['slug' => 'mahmoud', 'status' => Store::STATUS_ACTIVE]);
        $this->product = Product::factory()->for($this->store)->create([
            'status' => Product::STATUS_ACTIVE,
            'price' => 250,
            'track_stock' => false,
        ]);
    }

    private function order(array $overrides = []): array
    {
        return array_merge([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'customer_name' => 'محمود إسماعيل',
            'customer_phone' => '01001234567',
            'governorate' => 'القاهرة',
            'address' => 'شارع التحرير، وسط البلد',
            // A human took long enough to type all of the above.
            'form_opened_at' => Crypt::encryptString((string) (time() - 30)),
        ], $overrides);
    }

    /** Named `submit`, not `post` — overriding TestCase::post() recurses. */
    private function submit(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->post($this->store->canonicalUrl() . '/checkout', $payload);
    }

    public function test_a_real_customer_still_gets_through(): void
    {
        $this->submit($this->order());

        $this->assertSame(1, Order::count());
    }

    /** The trap field is off-screen; nothing but a form-filler touches it. */
    public function test_an_order_that_filled_the_honeypot_is_refused(): void
    {
        $this->submit($this->order(['confirm_url' => 'https://spam.example']))
            ->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    /** Nobody types a name, a phone and an address in under three seconds. */
    public function test_an_order_submitted_instantly_is_refused(): void
    {
        $this->submit($this->order(['form_opened_at' => Crypt::encryptString((string) time())]))
            ->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    /**
     * A cached page or an older form still open in somebody's browser must
     * not cost a real sale, so a missing or unreadable stamp is tolerated —
     * the honeypot and the rate limit both still apply.
     */
    public function test_a_missing_or_forged_stamp_does_not_block_a_sale(): void
    {
        $this->submit($this->order(['form_opened_at' => null]));
        $this->submit($this->order(['form_opened_at' => 'not-a-real-token']));

        $this->assertSame(2, Order::count());
    }

    public function test_a_flood_from_one_address_is_cut_off(): void
    {
        // Twenty an hour is far above any honest buyer and far below anything
        // that can empty a wallet.
        for ($i = 0; $i < 20; $i++) {
            $this->submit($this->order());
        }

        $this->assertSame(20, Order::count());

        $this->submit($this->order())->assertStatus(302);

        // The twenty-first never became an order, so it never became a charge.
        $this->assertSame(20, Order::count());
    }

    public function test_the_limit_is_per_store_not_global(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->submit($this->order());
        }

        // A second shop on the platform is untouched by the first one's flood —
        // otherwise one attacker could stop every store at once.
        $other = Store::factory()->create(['slug' => 'sarah', 'status' => Store::STATUS_ACTIVE]);
        $otherProduct = Product::factory()->for($other)->create([
            'status' => Product::STATUS_ACTIVE, 'price' => 100, 'track_stock' => false,
        ]);

        $this->post($other->canonicalUrl() . '/checkout', [
            'product_id' => $otherProduct->id,
            'quantity' => 1,
            'customer_name' => 'سارة',
            'customer_phone' => '01112223334',
            'governorate' => 'الجيزة',
            'address' => 'المهندسين',
            'form_opened_at' => Crypt::encryptString((string) (time() - 30)),
        ]);

        $this->assertSame(21, Order::count());
    }
}

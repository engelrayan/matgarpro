<?php

namespace Tests\Feature\Carts;

use App\Models\AbandonedCart;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbandonedCartTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create(['status' => Store::STATUS_ACTIVE]);
        $this->product = Product::factory()->for($this->store)->create(['price' => 399]);
    }

    private function beacon(array $payload = []): void
    {
        $this->post('http://' . $this->store->platformHost() . '/checkout/start', [
            'product_id' => $this->product->id,
            'quantity' => 1,
            ...$payload,
        ])->assertNoContent();
    }

    // ── Capture ─────────────────────────────────────────────────────────────

    public function test_a_half_filled_form_is_kept(): void
    {
        $this->beacon(['customer_name' => 'سارة', 'customer_phone' => '01223344556', 'governorate' => 'الإسكندرية']);

        $cart = AbandonedCart::firstOrFail();

        $this->assertSame('سارة', $cart->customer_name);
        $this->assertSame('01223344556', $cart->customer_phone);
        $this->assertSame('الإسكندرية', $cart->governorate);
    }

    /** Before a phone there is nothing to chase; the funnel already counts it. */
    public function test_nothing_is_kept_without_a_reachable_phone(): void
    {
        $this->beacon(['customer_name' => 'سارة']);

        $this->assertSame(0, AbandonedCart::count());
    }

    /** Someone correcting their number should leave one row, not four. */
    public function test_retyping_updates_the_same_cart(): void
    {
        $this->beacon(['customer_phone' => '01223344550']);
        $this->beacon(['customer_phone' => '01223344556', 'customer_name' => 'سارة']);
        $this->beacon(['customer_phone' => '01223344556', 'governorate' => 'القاهرة']);

        $this->assertSame(1, AbandonedCart::count());
        $this->assertSame('القاهرة', AbandonedCart::first()->governorate);
    }

    public function test_arabic_digits_are_normalised(): void
    {
        $this->beacon(['customer_phone' => '٠١٢٢٣٣٤٤٥٥٦']);

        $this->assertSame('01223344556', AbandonedCart::first()->customer_phone);
    }

    /** A showroom's visitors are us — they are not a merchant's leads. */
    public function test_a_showroom_keeps_no_carts(): void
    {
        $demo = Store::factory()->create(['is_demo' => true, 'status' => Store::STATUS_ACTIVE]);
        $product = Product::factory()->for($demo)->create();

        $this->post('http://' . $demo->platformHost() . '/checkout/start', [
            'product_id' => $product->id,
            'customer_phone' => '01223344556',
        ]);

        $this->assertSame(0, AbandonedCart::count());
    }

    // ── Recovery ────────────────────────────────────────────────────────────

    /**
     * The failure that would embarrass a merchant: chasing a customer about an
     * order they already placed.
     */
    public function test_placing_the_order_closes_the_cart(): void
    {
        $this->beacon(['customer_name' => 'سارة', 'customer_phone' => '01223344556']);

        $this->post('http://' . $this->store->platformHost() . '/checkout', [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'customer_name' => 'سارة',
            'customer_phone' => '01223344556',
            'address' => 'القاهرة',
        ])->assertRedirect();

        $cart = AbandonedCart::firstOrFail();

        $this->assertTrue($cart->isRecovered());
        $this->assertNotNull($cart->recovered_order_id);
    }

    /** People start on a phone and finish on a laptop. */
    public function test_a_cart_is_closed_by_the_same_phone_from_another_device(): void
    {
        $this->beacon(['customer_phone' => '01223344556']);

        // A fresh session: no visitor cookie carried over.
        $this->flushSession();

        $this->post('http://' . $this->store->platformHost() . '/checkout', [
            'product_id' => $this->product->id,
            'quantity' => 1,
            'customer_name' => 'سارة',
            'customer_phone' => '01223344556',
            'address' => 'القاهرة',
        ]);

        $this->assertTrue(AbandonedCart::firstOrFail()->isRecovered());
    }

    // ── The merchant's list ─────────────────────────────────────────────────

    private function merchant(): User
    {
        $user = User::factory()->create();
        $this->store->update(['user_id' => $user->id]);

        return $user;
    }

    public function test_the_list_shows_open_carts_with_their_value(): void
    {
        $this->beacon(['customer_name' => 'سارة', 'customer_phone' => '01223344556', 'quantity' => 2]);

        $this->actingAs($this->merchant())
            ->get('http://localhost/carts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('carts/Index')
                ->has('carts.data', 1)
                ->where('carts.data.0.customer_phone', '01223344556')
                // 2 × 399
                ->where('carts.data.0.value', '798.00')
                ->where('summary.open', 1));
    }

    public function test_recovered_carts_are_filtered_out_of_the_open_list(): void
    {
        $cart = AbandonedCart::create([
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'visitor_id' => 'v1',
            'customer_phone' => '01223344556',
            'recovered_at' => now(),
        ]);

        $user = $this->merchant();

        $this->actingAs($user)->get('http://localhost/carts')
            ->assertInertia(fn ($page) => $page->has('carts.data', 0));

        $this->actingAs($user)->get('http://localhost/carts?filter=recovered')
            ->assertInertia(fn ($page) => $page->has('carts.data', 1));

        $this->assertTrue($cart->fresh()->isRecovered());
    }

    public function test_a_merchant_can_mark_a_cart_as_contacted(): void
    {
        $this->beacon(['customer_phone' => '01223344556']);
        $cart = AbandonedCart::firstOrFail();

        $this->actingAs($this->merchant())
            ->patch('http://localhost/carts/' . $cart->id . '/contacted')
            ->assertRedirect();

        $this->assertNotNull($cart->fresh()->contacted_at);
    }

    public function test_a_merchant_cannot_touch_another_stores_cart(): void
    {
        $theirs = AbandonedCart::create([
            'store_id' => Store::factory()->create()->id,
            'visitor_id' => 'v1',
            'customer_phone' => '01223344556',
        ]);

        $this->actingAs($this->merchant())
            ->patch('http://localhost/carts/' . $theirs->id . '/contacted')
            ->assertForbidden();

        $this->assertNull($theirs->fresh()->contacted_at);
    }
}

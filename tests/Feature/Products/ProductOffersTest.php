<?php

namespace Tests\Feature\Products;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Quantity offers — "اشتري قطعتين بـ 400": set up by the merchant on the
 * product, picked by the customer instead of a quantity, a size chosen for
 * every piece, and the order priced from the offer row however the form is
 * edited on its way to us.
 */
class ProductOffersTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = User::factory()->create();
        $this->store = Store::factory()->for($this->merchant)->create(['slug' => 'mahmoud']);
        $this->product = Product::factory()->for($this->store)->create([
            'price' => 250, 'compare_at_price' => 270, 'stock' => 50,
        ]);
    }

    /** @param array<string,mixed> $attributes */
    private function offer(array $attributes = [], ?Product $product = null): ProductOffer
    {
        return ($product ?? $this->product)->offers()->create([
            'quantity' => 2, 'unit_price' => 200, ...$attributes,
        ]);
    }

    /** @param array<string,mixed> $overrides */
    private function order(array $overrides = []): TestResponse
    {
        return $this->post('http://' . $this->store->platformHost() . '/checkout', array_merge([
            'product_id' => $this->product->id,
            'quantity' => 1,
            'customer_name' => 'محمود ممدوح',
            'customer_phone' => '01006262330',
            'governorate' => 'القاهرة',
            'address' => 'شارع الهرم، عمارة ٥',
        ], $overrides));
    }

    /** @param array<string,mixed> $overrides */
    private function save(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->merchant)->post("/products/{$this->product->id}", array_merge([
            'name' => $this->product->name,
            'price' => 250,
            'compare_at_price' => 270,
            'status' => Product::STATUS_ACTIVE,
        ], $overrides));
    }

    // ── The merchant ─────────────────────────────────────────────────────

    public function test_a_merchant_sets_up_offers_on_a_product(): void
    {
        $this->save(['offers' => [
            ['quantity' => 1, 'unit_price' => 250, 'title' => '', 'free_shipping' => '0', 'is_default' => '0'],
            ['quantity' => 2, 'unit_price' => 200, 'badge' => 'الأكثر طلبًا', 'free_shipping' => '1', 'is_default' => '1'],
        ]])->assertSessionHasNoErrors();

        [$one, $two] = $this->product->offers()->get();

        $this->assertSame(1, $one->quantity);
        $this->assertNull($one->title);
        $this->assertSame('اشتري قطعة بـ 250', $one->label());
        $this->assertSame('اشتري قطعتين بـ 400', $two->label());
        $this->assertTrue($two->free_shipping);
        $this->assertTrue($two->is_default);
        $this->assertSame('الأكثر طلبًا', $two->badge);
    }

    public function test_editing_keeps_offers_by_id_and_drops_the_removed_ones(): void
    {
        $kept = $this->offer(['quantity' => 2]);
        $this->offer(['quantity' => 3]);

        $this->save(['offers' => [
            ['id' => $kept->id, 'quantity' => 2, 'unit_price' => 190],
        ]])->assertSessionHasNoErrors();

        $this->assertSame([$kept->id], $this->product->offers()->pluck('id')->all());
        $this->assertSame('190.00', $kept->fresh()->unit_price);
    }

    public function test_removing_every_offer_removes_them(): void
    {
        $this->offer();

        // What the form sends once the list is empty: FormData has no empty list.
        $this->save(['offers' => ''])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->product->offers()->count());
    }

    public function test_saving_without_the_offers_field_leaves_them_alone(): void
    {
        $this->offer();

        $this->save()->assertSessionHasNoErrors();

        $this->assertSame(1, $this->product->offers()->count());
    }

    public function test_another_products_offer_cannot_be_edited_through_this_one(): void
    {
        $theirs = $this->offer(['unit_price' => 500], Product::factory()->for(Store::factory()->create())->create());

        $this->save(['offers' => [['id' => $theirs->id, 'quantity' => 2, 'unit_price' => 1]]])->assertSessionHasNoErrors();

        $this->assertSame('500.00', $theirs->fresh()->unit_price);
        $this->assertSame(1, $this->product->offers()->count());
    }

    public function test_two_offers_for_the_same_number_of_pieces_are_refused(): void
    {
        $this->save(['offers' => [
            ['quantity' => 2, 'unit_price' => 200],
            ['quantity' => 2, 'unit_price' => 180],
        ]])->assertSessionHasErrors('offers.1.quantity');
    }

    public function test_only_one_offer_starts_selected(): void
    {
        $this->save(['offers' => [
            ['quantity' => 1, 'unit_price' => 250, 'is_default' => '1'],
            ['quantity' => 2, 'unit_price' => 200, 'is_default' => '1'],
        ]]);

        $this->assertSame([true, false], $this->product->offers()->pluck('is_default')->all());
    }

    public function test_duplicating_a_product_copies_its_offers(): void
    {
        $this->offer(['badge' => 'الأكثر طلبًا']);

        $this->actingAs($this->merchant)->post("/products/{$this->product->id}/duplicate");

        $copy = Product::whereKeyNot($this->product->id)->sole();
        $this->assertSame('الأكثر طلبًا', $copy->offers()->sole()->badge);
    }

    // ── The customer ─────────────────────────────────────────────────────

    public function test_the_product_page_offers_the_choice_instead_of_a_quantity(): void
    {
        $this->offer(['quantity' => 1, 'unit_price' => 250]);
        $this->offer(['quantity' => 2, 'unit_price' => 200, 'is_default' => true]);

        $this->get('http://' . $this->store->platformHost() . '/p/' . $this->product->slug)
            ->assertOk()
            ->assertSee('اختار العرض المناسب')
            ->assertSee('اشتري قطعتين بـ 400')
            // Against the struck "before" price, 270 a piece.
            ->assertSee('هتوفّر <span class="tabular">140</span>', escape: false)
            ->assertDontSee('data-step="1"', escape: false);
    }

    public function test_an_offer_is_priced_from_the_offer_not_the_form(): void
    {
        $offer = $this->offer(['quantity' => 2, 'unit_price' => 200]);

        $this->order(['offer_id' => $offer->id, 'quantity' => 2, 'unit_price' => 1, 'total' => 1])->assertRedirect();

        $order = Order::sole();
        $this->assertSame('400.00', $order->total);
        $this->assertSame(2, $order->items->sole()->quantity);
        $this->assertSame('اشتري قطعتين بـ 400', $order->items->sole()->offer_label);
        $this->assertSame(48, $this->product->fresh()->stock);
    }

    public function test_the_offer_price_cannot_be_had_for_fewer_pieces(): void
    {
        $offer = $this->offer(['quantity' => 3, 'unit_price' => 150]);

        $this->order(['offer_id' => $offer->id, 'quantity' => 1])->assertSessionHasErrors('checkout');

        $this->assertSame(0, Order::count());
    }

    public function test_a_product_with_offers_is_only_sold_through_them(): void
    {
        $this->offer(['quantity' => 2]);

        $this->order(['quantity' => 1])->assertSessionHasErrors('checkout');

        $this->assertSame(0, Order::count());
    }

    public function test_another_products_offer_is_refused(): void
    {
        $this->offer(['quantity' => 2]);
        $theirs = $this->offer(['quantity' => 1, 'unit_price' => 1], Product::factory()->for($this->store)->create());

        $this->order(['offer_id' => $theirs->id, 'quantity' => 1])->assertSessionHasErrors('checkout');

        $this->assertSame(0, Order::count());
    }

    public function test_a_product_without_offers_sells_as_before(): void
    {
        $this->order(['quantity' => 2, 'offer_id' => 999])->assertRedirect();

        $this->assertSame('500.00', Order::sole()->total);
        $this->assertNull(Order::sole()->items->sole()->offer_label);
    }

    // ── A size for every piece ───────────────────────────────────────────

    public function test_each_piece_takes_its_own_size(): void
    {
        $shirt = Product::factory()->for($this->store)->withVariants()->create(['price' => 300]);
        $offer = $this->offer(['quantity' => 3, 'unit_price' => 250], $shirt);

        $redM = $this->variant($shirt, 'أحمر', 'M');
        $blueL = $this->variant($shirt, 'أزرق', 'L');

        $this->order([
            'product_id' => $shirt->id,
            'offer_id' => $offer->id,
            'quantity' => 3,
            'pieces' => [$redM->id, $blueL->id, $redM->id],
        ])->assertRedirect();

        $order = Order::sole();
        $this->assertSame('750.00', $order->total);

        $lines = $order->items->keyBy('product_variant_id');
        $this->assertSame(2, $lines[$redM->id]->quantity);
        $this->assertSame(1, $lines[$blueL->id]->quantity);
        $this->assertSame('250.00', $lines[$blueL->id]->unit_price);

        $this->assertSame(3, $redM->fresh()->stock);
        $this->assertSame(4, $blueL->fresh()->stock);
    }

    public function test_pieces_that_do_not_add_up_to_the_offer_are_refused(): void
    {
        $shirt = Product::factory()->for($this->store)->withVariants()->create();
        $offer = $this->offer(['quantity' => 3, 'unit_price' => 100], $shirt);
        $redM = $this->variant($shirt, 'أحمر', 'M');

        $this->order([
            'product_id' => $shirt->id,
            'offer_id' => $offer->id,
            'quantity' => 3,
            'pieces' => [$redM->id, $redM->id],
        ])->assertSessionHasErrors('checkout');

        $this->assertSame(0, Order::count());
    }

    public function test_pieces_of_one_size_are_counted_against_its_stock_together(): void
    {
        $shirt = Product::factory()->for($this->store)->withVariants()->create();
        $offer = $this->offer(['quantity' => 2], $shirt);
        $redM = $this->variant($shirt, 'أحمر', 'M');
        $redM->update(['stock' => 1]);

        $this->order([
            'product_id' => $shirt->id,
            'offer_id' => $offer->id,
            'quantity' => 2,
            'pieces' => [$redM->id, $redM->id],
        ])->assertSessionHasErrors('checkout');

        $this->assertSame(1, $redM->fresh()->stock);
    }

    // ── Delivery ─────────────────────────────────────────────────────────

    public function test_a_free_shipping_offer_ships_free_but_not_everywhere(): void
    {
        $this->store->update(['settings' => [...(array) $this->store->settings, 'shipping' => [
            'enabled' => true,
            'carrier' => 'self',
            'zones' => ['cairo' => 50, 'delta' => 60, 'upper' => 75, 'remote' => 100],
            'governorates' => ['شمال سيناء' => ['price' => null, 'closed' => true]],
        ]]]);

        $free = $this->offer(['quantity' => 2, 'free_shipping' => true]);
        $paid = $this->offer(['quantity' => 1, 'unit_price' => 250]);

        $this->order(['offer_id' => $free->id, 'quantity' => 2])->assertRedirect();
        $this->assertSame('0.00', Order::sole()->shipping_amount);

        $this->order(['offer_id' => $paid->id, 'quantity' => 1, 'customer_phone' => '01112223334'])->assertRedirect();
        $this->assertSame('50.00', Order::latest('id')->first()->shipping_amount);

        $this->order(['offer_id' => $free->id, 'quantity' => 2, 'governorate' => 'شمال سيناء'])
            ->assertSessionHasErrors('governorate');
    }

    // ── Afterwards ───────────────────────────────────────────────────────

    public function test_the_merchant_sees_the_offer_on_the_order_and_the_waybill(): void
    {
        $offer = $this->offer(['quantity' => 2, 'title' => 'عرض العيد: قطعتين']);
        $this->order(['offer_id' => $offer->id, 'quantity' => 2]);
        $order = Order::sole();

        $this->actingAs($this->merchant)
            ->get("http://localhost/orders/{$order->id}")
            ->assertInertia(fn ($page) => $page->where('order.items.0.offer_label', 'عرض العيد: قطعتين'));

        $this->actingAs($this->merchant)
            ->get("http://localhost/orders/waybills?ids={$order->id}")
            ->assertSee('العرض: عرض العيد: قطعتين');
    }

    private function variant(Product $product, string $colour, string $size): ProductVariant
    {
        return $product->variants()->get()->first(
            fn (ProductVariant $v) => $v->options === ['اللون' => $colour, 'المقاس' => $size],
        );
    }
}

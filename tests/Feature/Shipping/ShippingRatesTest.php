<?php

namespace Tests\Feature\Shipping;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreDamanIntegration;
use App\Models\User;
use App\Services\Shipping\ShippingRates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shipping priced by governorate: set by the merchant per zone, overridden per
 * governorate, shown to the customer before they order, and charged once on
 * the order — never more, never on a free-shipping product, and never to a
 * governorate the merchant does not deliver to.
 */
class ShippingRatesTest extends TestCase
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
        $this->product = Product::factory()->for($this->store)->create(['price' => 300, 'stock' => 50]);
    }

    /** @param array<string,mixed> $overrides */
    private function priceShipping(array $overrides = []): void
    {
        $this->store->update(['settings' => [...(array) $this->store->settings, 'shipping' => array_merge([
            'enabled' => true,
            'carrier' => 'self',
            'zones' => ['cairo' => 50, 'delta' => 60, 'upper' => 75, 'remote' => 100],
            'governorates' => [
                'الجيزة' => ['price' => 55, 'closed' => false],
                'شمال سيناء' => ['price' => null, 'closed' => true],
            ],
        ], $overrides)]]);
    }

    /** @param array<string,mixed> $overrides */
    private function order(array $overrides = []): \Illuminate\Testing\TestResponse
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

    // ── The zones ─────────────────────────────────────────────────────────

    public function test_every_governorate_sits_in_exactly_one_zone(): void
    {
        $zoned = collect(config('egypt.shipping_zones'))->flatMap(fn ($zone) => $zone['governorates']);

        // A governorate in no zone would have no price and drop out of the
        // order form; one in two zones would be priced by whichever came first.
        $this->assertEqualsCanonicalizing(config('egypt.governorates'), $zoned->all());
        $this->assertSame($zoned->count(), $zoned->unique()->count());
    }

    // ── Prices off: exactly what stores had before ────────────────────────

    public function test_with_prices_off_nothing_changes(): void
    {
        $this->order(['governorate' => 'أسوان'])->assertRedirect();

        $order = Order::sole();
        $this->assertSame('0.00', $order->shipping_amount);
        $this->assertSame('300.00', $order->total);

        $this->get('http://' . $this->store->platformHost() . '/p/' . $this->product->slug)
            ->assertOk()
            ->assertDontSee('id="shippingAmount"', escape: false);
    }

    // ── Prices on ─────────────────────────────────────────────────────────

    public function test_the_zone_price_is_charged_and_written_onto_the_order(): void
    {
        $this->priceShipping();

        $this->order(['governorate' => 'الإسكندرية'])->assertRedirect();

        $order = Order::sole();
        $this->assertSame('300.00', $order->subtotal);
        $this->assertSame('60.00', $order->shipping_amount);
        $this->assertSame('360.00', $order->total);
    }

    public function test_a_governorate_with_its_own_price_beats_its_zone(): void
    {
        $this->priceShipping();

        $this->order(['governorate' => 'الجيزة'])->assertRedirect();

        $this->assertSame('55.00', Order::sole()->shipping_amount);
    }

    public function test_shipping_is_charged_once_whatever_the_quantity(): void
    {
        $this->priceShipping();

        $this->order(['governorate' => 'أسوان', 'quantity' => 3])->assertRedirect();

        $order = Order::sole();
        $this->assertSame('75.00', $order->shipping_amount);
        $this->assertSame('975.00', $order->total);
    }

    public function test_a_free_shipping_product_ships_free(): void
    {
        $this->priceShipping();
        $this->product->update(['settings' => ['free_shipping' => true]]);

        $this->order(['governorate' => 'أسوان'])->assertRedirect();

        $order = Order::sole();
        $this->assertSame('0.00', $order->shipping_amount);
        $this->assertSame('300.00', $order->total);
    }

    public function test_a_governorate_the_merchant_closed_cannot_order(): void
    {
        $this->priceShipping();

        $this->order(['governorate' => 'شمال سيناء'])
            ->assertSessionHasErrors(['governorate' => 'للأسف مش بنوصّل للمحافظة دي.']);

        $this->assertSame(0, Order::count());
    }

    public function test_the_governorate_becomes_required_even_if_the_merchant_hid_it(): void
    {
        $this->store->update(['settings' => ['checkout_fields' => [
            'governorate' => ['label' => 'المحافظة', 'placeholder' => '', 'enabled' => false, 'required' => false, 'order' => 3],
        ]]]);
        $this->priceShipping();

        $this->order(['governorate' => ''])->assertSessionHasErrors('governorate');
        $this->assertSame(0, Order::count());

        $field = $this->store->fresh()->checkoutFields()['governorate'];
        $this->assertTrue($field['enabled']);
        $this->assertTrue($field['required']);
    }

    public function test_an_order_keeps_the_price_it_was_taken_at(): void
    {
        $this->priceShipping();
        $this->order(['governorate' => 'الإسكندرية'])->assertRedirect();

        $this->priceShipping(['zones' => ['cairo' => 50, 'delta' => 90, 'upper' => 75, 'remote' => 100]]);

        $this->assertSame('60.00', Order::sole()->fresh()->shipping_amount);
    }

    public function test_the_order_form_offers_only_open_governorates_with_their_price(): void
    {
        $this->priceShipping();

        $html = $this->get('http://' . $this->store->platformHost() . '/p/' . $this->product->slug)
            ->assertOk()
            ->assertSee('id="shippingAmount"', escape: false)
            ->assertDontSee('value="شمال سيناء"', escape: false)
            ->getContent();

        $this->assertMatchesRegularExpression('/value="الجيزة"\s+data-shipping="55"/u', $html);
        $this->assertMatchesRegularExpression('/value="أسوان"\s+data-shipping="75"/u', $html);
    }

    public function test_the_customer_and_the_courier_both_see_the_shipping_line(): void
    {
        $this->priceShipping();

        $redirect = $this->order(['governorate' => 'أسوان'])->headers->get('Location');
        $order = Order::sole();

        $this->actingAs($this->merchant)
            // The dashboard host, spelled out: the checkout above ran on the
            // store's own host, and a relative URL would stay there.
            ->get('http://localhost/orders/waybills?ids=' . $order->id)
            ->assertOk()
            ->assertSee('الشحن')
            ->assertSee('375.00');

        $this->get($redirect)->assertOk()->assertSee('الشحن (أسوان)', escape: false)->assertSee('375.00');
    }

    // ── The settings screen ───────────────────────────────────────────────

    /** @param array<string,mixed> $overrides */
    private function save(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->merchant)->put('/settings/shipping', array_merge([
            'enabled' => true,
            'carrier' => 'self',
            'zones' => ['cairo' => '50', 'delta' => '60', 'upper' => '75', 'remote' => '100'],
            'governorates' => [
                'الجيزة' => ['price' => '55', 'closed' => false],
                'مطروح' => ['price' => null, 'closed' => true],
                'القاهرة' => ['price' => null, 'closed' => false],
            ],
        ], $overrides));
    }

    public function test_the_settings_page_opens(): void
    {
        $this->actingAs($this->merchant)
            ->get('/settings/shipping')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('settings/Shipping')
                ->where('enabled', false)
                ->where('carrier', 'self')
                ->has('zones', 4));
    }

    public function test_a_merchant_saves_zone_prices_and_only_the_exceptions_are_kept(): void
    {
        $this->save()->assertSessionHasNoErrors();

        $saved = $this->store->fresh()->settings['shipping'];

        $this->assertTrue($saved['enabled']);
        // assertEquals: a JSON column hands 60.0 back as 60.
        $this->assertEquals(60, $saved['zones']['delta']);
        $this->assertEquals([
            'الجيزة' => ['price' => 55, 'closed' => false],
            'مطروح' => ['price' => null, 'closed' => true],
        ], $saved['governorates']);
    }

    public function test_an_open_zone_without_a_price_is_refused(): void
    {
        $this->save(['zones' => ['cairo' => '50', 'delta' => '', 'upper' => '75', 'remote' => '100']])
            ->assertSessionHasErrors('zones.delta');

        $this->assertNull(data_get($this->store->fresh()->settings, 'shipping'));
    }

    public function test_a_zone_needs_no_price_when_all_its_governorates_are_closed(): void
    {
        $this->save([
            'zones' => ['cairo' => '50', 'delta' => '60', 'upper' => '75', 'remote' => ''],
            'governorates' => collect(config('egypt.shipping_zones.remote.governorates'))
                ->mapWithKeys(fn ($g) => [$g => ['price' => null, 'closed' => true]])
                ->all(),
        ])->assertSessionHasNoErrors();
    }

    public function test_free_is_a_price(): void
    {
        $this->save(['zones' => ['cairo' => '0', 'delta' => '60', 'upper' => '75', 'remote' => '100']])
            ->assertSessionHasNoErrors();

        $this->order(['governorate' => 'القاهرة'])->assertRedirect();

        $this->assertSame('0.00', Order::sole()->shipping_amount);
    }

    public function test_negative_and_unknown_values_are_refused_or_ignored(): void
    {
        $this->save(['zones' => ['cairo' => '-5']])->assertSessionHasErrors('zones.cairo');

        $this->save(['zones' => ['cairo' => '50', 'delta' => '60', 'upper' => '75', 'remote' => '100', 'mars' => '1'],
            'governorates' => ['أورشليم' => ['price' => '1', 'closed' => false]]])
            ->assertSessionHasNoErrors();

        $saved = $this->store->fresh()->settings['shipping'];
        $this->assertArrayNotHasKey('mars', $saved['zones']);
        $this->assertArrayNotHasKey('أورشليم', $saved['governorates']);
    }

    public function test_settings_are_saved_on_the_merchants_own_store_only(): void
    {
        $other = Store::factory()->create();

        $this->save()->assertSessionHasNoErrors();

        $this->assertNull(data_get($other->fresh()->settings, 'shipping'));
    }

    public function test_the_merchants_own_form_choice_survives_while_shipping_forces_the_governorate(): void
    {
        $this->store->update(['settings' => ['checkout_fields' => [
            'governorate' => ['label' => 'المحافظة', 'placeholder' => '', 'enabled' => false, 'required' => false, 'order' => 3],
        ]]]);
        $this->priceShipping();

        // The form screen posts the forced, locked-on value back.
        $fields = collect($this->store->fresh()->checkoutFields())
            ->map(fn ($f, $key) => [...$f, 'key' => $key])->values()->all();
        $this->actingAs($this->merchant)->put('/settings/checkout', ['fields' => $fields])->assertSessionHasNoErrors();

        $this->priceShipping(['enabled' => false]);

        $this->assertFalse($this->store->fresh()->checkoutFields()['governorate']['enabled']);
    }

    // ── Who carries the parcels ───────────────────────────────────────────

    public function test_the_daman_button_shows_only_when_daman_is_the_carrier(): void
    {
        $this->store->damanIntegration()->create([
            'api_key' => 'dm_test_' . str_repeat('k', 32),
            'key_prefix' => 'dm_test_kkkkkkkk',
            'environment' => 'test',
            'is_active' => true,
            'webhook_token' => StoreDamanIntegration::newWebhookToken(),
            'connected_at' => now(),
        ]);

        // Never chose: connected to Daman, so Daman.
        $this->assertSame(ShippingRates::CARRIER_DAMAN, ShippingRates::for($this->store->fresh())->carrier());
        $this->actingAs($this->merchant)->get('/orders')
            ->assertInertia(fn ($page) => $page->where('daman_enabled', true));

        $this->priceShipping(['carrier' => 'self']);

        $this->actingAs($this->merchant)->get('/orders')
            ->assertInertia(fn ($page) => $page->where('daman_enabled', false));
    }
}

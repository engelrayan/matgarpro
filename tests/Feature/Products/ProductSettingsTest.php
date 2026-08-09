<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->store = Store::factory()->for($this->user)->create(['slug' => 'mahmoud']);
    }

    private function url(Product $product): string
    {
        return 'http://' . $this->store->platformHost() . '/p/' . $product->slug;
    }

    /** A product saved before a toggle existed still reads every current key. */
    public function test_settings_fall_back_to_the_platform_defaults(): void
    {
        $product = Product::factory()->for($this->store)->create(['settings' => ['free_shipping' => true]]);

        $settings = $product->pageSettings();

        $this->assertTrue($settings['free_shipping']);
        $this->assertSame(config('products.defaults.buy_button_text'), $settings['buy_button_text']);
        $this->assertArrayHasKey('hide_header', $settings);
    }

    public function test_the_storefront_uses_the_merchants_button_text(): void
    {
        $product = Product::factory()->for($this->store)->create([
            'settings' => ['buy_button_text' => 'اشتري دلوقتي'],
        ]);

        $this->get($this->url($product))
            ->assertOk()
            ->assertSee('اشتري دلوقتي')
            ->assertDontSee('اطلب دلوقتي');
    }

    public function test_free_shipping_and_sticky_bar_render(): void
    {
        $product = Product::factory()->for($this->store)->create([
            'settings' => ['free_shipping' => true, 'sticky_buy_bar' => true],
        ]);

        $this->get($this->url($product))
            ->assertOk()
            ->assertSee('شحن مجاني')
            ->assertSee('id="stickyBar"', false);
    }

    public function test_hiding_the_header_removes_it(): void
    {
        $product = Product::factory()->for($this->store)->create(['settings' => ['hide_header' => true]]);

        $this->get($this->url($product))->assertOk()->assertDontSee('<header', false);
    }

    /**
     * Out of stock is not the same as gone. The default keeps the page up —
     * it still earns search traffic and still lets a customer ask.
     */
    public function test_a_sold_out_product_stays_visible_by_default(): void
    {
        $product = Product::factory()->for($this->store)->create(['track_stock' => true, 'stock' => 0]);

        $this->get($this->url($product))->assertOk();
    }

    public function test_a_sold_out_product_can_be_hidden_on_request(): void
    {
        $product = Product::factory()->for($this->store)->create([
            'track_stock' => true,
            'stock' => 0,
            'settings' => ['hide_when_out_of_stock' => true],
        ]);

        $this->get($this->url($product))->assertNotFound();
    }

    /** Untracked stock means unlimited, so zero must not hide anything. */
    public function test_untracked_stock_is_never_treated_as_sold_out(): void
    {
        $product = Product::factory()->for($this->store)->create([
            'track_stock' => false,
            'stock' => 0,
            'settings' => ['hide_when_out_of_stock' => true],
        ]);

        $this->get($this->url($product))->assertOk();
    }

    /** Only keys we ship may be stored — a JSON column is not a free-for-all. */
    public function test_unknown_settings_are_discarded_on_save(): void
    {
        $this->actingAs($this->user)->post('http://localhost/products', [
            'name' => 'قميص',
            'price' => 300,
            'status' => 'active',
            'settings' => [
                'buy_button_text' => 'اشتري',
                'fake_visitor_counter' => true,
                'pixel_price' => 10,
            ],
        ])->assertSessionHasNoErrors();

        $stored = Product::firstOrFail()->settings;

        $this->assertSame('اشتري', $stored['buy_button_text']);
        $this->assertArrayNotHasKey('fake_visitor_counter', $stored);
        $this->assertArrayNotHasKey('pixel_price', $stored);
    }

    /** An empty label would render a button with no words on it. */
    public function test_a_blank_button_label_falls_back_to_the_default(): void
    {
        $this->actingAs($this->user)->post('http://localhost/products', [
            'name' => 'قميص',
            'price' => 300,
            'status' => 'active',
            'settings' => ['buy_button_text' => '   '],
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            config('products.defaults.buy_button_text'),
            Product::firstOrFail()->setting('buy_button_text'),
        );
    }
}

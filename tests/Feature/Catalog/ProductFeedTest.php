<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The feed is consumed by machines that reject silently — a bad row simply
 * never appears, and the merchant spends a week wondering why half their
 * catalogue is missing. These assert the traps, not the happy path.
 */
class ProductFeedTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create(['status' => Store::STATUS_ACTIVE, 'currency' => 'EGP']);
    }

    private function product(array $attributes = [], string $image = 'products/x.jpg'): Product
    {
        $product = Product::factory()->for($this->store)->create($attributes);

        if ($image) {
            ProductImage::create(['product_id' => $product->id, 'path' => $image, 'sort_order' => 0]);
        }

        return $product;
    }

    private function feed(string $platform = 'meta'): string
    {
        return $this->get('http://' . $this->store->platformHost() . "/feed/{$platform}.xml")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();
    }

    public function test_the_feed_is_valid_xml(): void
    {
        $this->product(['name' => 'قميص قطن', 'price' => 399]);

        $xml = simplexml_load_string($this->feed());

        $this->assertNotFalse($xml, 'the feed must parse');
        $this->assertSame(1, $xml->channel->item->count());
    }

    /**
     * Names in this market carry `&` routinely. One unescaped character
     * invalidates the whole document, and the platform reports only "feed could
     * not be parsed" with no hint which row did it.
     */
    public function test_ampersands_and_angle_brackets_do_not_break_the_document(): void
    {
        $this->product(['name' => 'قميص & بنطلون <جديد>', 'price' => 399]);

        $xml = simplexml_load_string($this->feed());

        $this->assertNotFalse($xml);
        $this->assertStringContainsString('قميص & بنطلون <جديد>', $this->feed());
    }

    /** Platforms fetch from their own servers, where `/storage/…` is nothing. */
    public function test_image_links_are_absolute(): void
    {
        $this->product();

        preg_match('#<g:image_link><!\[CDATA\[(.*?)\]\]></g:image_link>#', $this->feed(), $m);

        $this->assertStringStartsWith('http', $m[1] ?? '');
    }

    public function test_a_product_with_no_image_is_left_out(): void
    {
        $this->product(['name' => 'من غير صورة'], image: '');
        $this->product(['name' => 'بصورة']);

        $xml = simplexml_load_string($this->feed());

        $this->assertSame(1, $xml->channel->item->count());
        $this->assertStringNotContainsString('من غير صورة', $this->feed());
    }

    public function test_draft_products_never_reach_the_feed(): void
    {
        $this->product(['name' => 'مسودة', 'status' => Product::STATUS_DRAFT]);

        $this->assertStringNotContainsString('مسودة', $this->feed());
    }

    public function test_stock_drives_availability(): void
    {
        $this->product(['name' => 'خلص', 'track_stock' => true, 'stock' => 0]);

        $this->assertStringContainsString('out of stock', $this->feed());
    }

    /** Markup in the description is rejected; the words must survive it. */
    public function test_html_is_stripped_without_running_words_together(): void
    {
        $this->product([
            'name' => 'قميص',
            'description' => '<p>خامة ممتازة</p><ul><li>الدفع عند الاستلام</li><li>استبدال</li></ul>',
        ]);

        $feed = $this->feed();

        $this->assertStringNotContainsString('<p>', $feed);
        $this->assertStringContainsString('الدفع عند الاستلام استبدال', $feed);
    }

    /**
     * A sale is a struck-through price plus a live one. Sending `sale_price`
     * always would make every product look permanently discounted.
     */
    public function test_only_a_real_sale_sends_a_sale_price(): void
    {
        $this->product(['name' => 'عادي', 'price' => 400, 'compare_at_price' => null]);

        $this->assertStringNotContainsString('g:sale_price', $this->feed());
    }

    public function test_a_discounted_product_sends_both_prices(): void
    {
        $this->product(['name' => 'مخفّض', 'price' => 300, 'compare_at_price' => 500]);

        $feed = $this->feed();

        $this->assertStringContainsString('<g:price><![CDATA[500.00 EGP]]></g:price>', $feed);
        $this->assertStringContainsString('<g:sale_price><![CDATA[300.00 EGP]]></g:sale_price>', $feed);
    }

    /**
     * A clothing product listed as one row means a sold-out medium reads as the
     * whole product being gone. Variants must be their own grouped items.
     */
    public function test_variants_become_grouped_items(): void
    {
        $product = $this->product(['name' => 'قميص', 'options' => [['name' => 'المقاس', 'values' => ['M', 'L']]]]);

        foreach (['M', 'L'] as $size) {
            $product->variants()->create(['options' => ['المقاس' => $size], 'stock' => 5]);
        }

        $xml = simplexml_load_string($this->feed());

        $this->assertSame(2, $xml->channel->item->count());
        $this->assertStringContainsString('<g:item_group_id><![CDATA[' . $product->id . ']]>', $this->feed());
        $this->assertStringContainsString('<g:size><![CDATA[M]]></g:size>', $this->feed());
    }

    // ── Access ──────────────────────────────────────────────────────────────

    /** A showroom's catalogue is fiction; it must never reach an ad platform. */
    public function test_a_showroom_serves_no_feed(): void
    {
        $demo = Store::factory()->create(['is_demo' => true, 'status' => Store::STATUS_ACTIVE]);

        $this->get('http://' . $demo->platformHost() . '/feed/meta.xml')->assertNotFound();
    }

    public function test_an_unknown_platform_is_rejected(): void
    {
        $this->get('http://' . $this->store->platformHost() . '/feed/pinterest.xml')->assertNotFound();
    }

    /** Raw XML in search results competes with the merchant's product pages. */
    public function test_the_feed_is_not_indexed(): void
    {
        $this->product();

        $this->get('http://' . $this->store->platformHost() . '/feed/meta.xml')
            ->assertHeader('X-Robots-Tag', 'noindex');
    }

    // ── The settings screen ─────────────────────────────────────────────────

    public function test_the_settings_page_renders_with_the_feed_links(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->for($user)->create();

        $this->actingAs($user)
            ->get('http://localhost/settings/catalog')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Catalog')
                ->where('feeds.meta', fn ($url) => str_contains((string) $url, '/feed/meta.xml')));
    }

    /**
     * SVG is not an accepted image format on any of the three platforms, and
     * our own sample artwork is SVG — a store still on it would publish nothing
     * and be told nothing.
     */
    public function test_svg_images_are_reported_as_a_blocker(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->for($user)->create();

        $product = Product::factory()->for($store)->create();
        ProductImage::create(['product_id' => $product->id, 'path' => 'samples/1.svg', 'sort_order' => 0]);

        $this->actingAs($user)
            ->get('http://localhost/settings/catalog')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('readiness.ready', 0)
                ->where('readiness.issues.0.level', 'error')
                ->where('readiness.issues.0.text', fn ($t) => str_contains((string) $t, 'SVG')));
    }
}

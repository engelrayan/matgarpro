<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The optional product video.
 *
 * Two things matter and both are here: a merchant can paste the link in
 * whatever shape their phone gave them, and nothing that is not a real YouTube
 * id ever reaches an iframe on a page customers buy from.
 */
class ProductVideoTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = User::factory()->create();
        $this->store = Store::factory()->for($this->merchant)->create(['slug' => 'mahmoud']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'قميص قطن',
            'price' => 250,
            'status' => Product::STATUS_ACTIVE,
        ], $overrides);
    }

    public function test_a_merchant_can_save_a_product_with_a_video(): void
    {
        $this->actingAs($this->merchant)
            ->post('/products', $this->payload(['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']))
            ->assertSessionHasNoErrors();

        $this->assertSame('dQw4w9WgXcQ', Product::first()->videoId());
    }

    /** Merchants paste whatever their phone's share sheet handed them. */
    public function test_every_shape_of_youtube_link_is_accepted(): void
    {
        $shapes = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'dQw4w9WgXcQ',
        ];

        foreach ($shapes as $shape) {
            $this->assertSame('dQw4w9WgXcQ', Video::youtubeId($shape), "failed on: {$shape}");
        }
    }

    public function test_a_link_that_is_not_a_video_is_refused(): void
    {
        $this->actingAs($this->merchant)
            ->post('/products', $this->payload(['video_url' => 'https://youtube.com/channel/whatever']))
            ->assertSessionHasErrors('video_url');

        $this->assertSame(0, Product::count());
    }

    public function test_a_javascript_url_never_becomes_a_video(): void
    {
        $this->actingAs($this->merchant)
            ->post('/products', $this->payload(['video_url' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('video_url');
    }

    public function test_leaving_it_blank_stores_null_not_an_empty_string(): void
    {
        $this->actingAs($this->merchant)
            ->post('/products', $this->payload(['video_url' => '']))
            ->assertSessionHasNoErrors();

        $product = Product::first();

        $this->assertNull($product->video_url);
        $this->assertNull($product->videoId());
    }

    // ── Storefront ──────────────────────────────────────────────────────

    public function test_the_video_shows_on_the_product_page(): void
    {
        $product = Product::factory()->for($this->store)->create([
            'slug' => 'shirt',
            'status' => Product::STATUS_ACTIVE,
            'video_url' => 'https://youtu.be/dQw4w9WgXcQ',
        ]);

        $this->get($this->store->canonicalUrl() . '/p/' . $product->slug)
            ->assertOk()
            ->assertSee('data-video="dQw4w9WgXcQ"', escape: false)
            // The player itself must NOT be on the page until it is clicked —
            // an iframe on load costs the merchant ~700KB of YouTube's JS per
            // visit, on a page they pay per visit to show.
            ->assertDontSee('<iframe', escape: false);
    }

    public function test_a_product_with_no_video_renders_no_video_block(): void
    {
        $product = Product::factory()->for($this->store)->create([
            'slug' => 'plain',
            'status' => Product::STATUS_ACTIVE,
            'video_url' => null,
        ]);

        $this->get($this->store->canonicalUrl() . '/p/' . $product->slug)
            ->assertOk()
            ->assertDontSee('data-video', escape: false)
            ->assertDontSee('شوف المنتج بالفيديو', escape: false);
    }
}

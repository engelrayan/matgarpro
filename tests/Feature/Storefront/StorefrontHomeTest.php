<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontHomeTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create(['status' => Store::STATUS_ACTIVE]);
    }

    private function url(string $path = '/'): string
    {
        return 'http://' . $this->store->platformHost() . $path;
    }

    private function product(array $attributes = []): Product
    {
        $product = Product::factory()->for($this->store)->create($attributes);

        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'demo/x.svg',
            'sort_order' => 0,
        ]);

        return $product;
    }

    // ── Hero ────────────────────────────────────────────────────────────────

    public function test_the_hero_leads_with_discounted_products(): void
    {
        $this->product(['name' => 'منتج عادي', 'price' => 100, 'sort_order' => 1]);
        $this->product(['name' => 'منتج مخفّض', 'price' => 80, 'compare_at_price' => 160, 'sort_order' => 2]);

        /*
         | Asserted against the rendered page rather than a `slides` view
         | variable: the home page is a section list now, and which section
         | produced the hero is an implementation detail. What has to stay true
         | is that the discounted product is the first thing on the page.
         */
        $this->get($this->url())
            ->assertOk()
            ->assertSeeInOrder(['منتج مخفّض', 'منتج عادي'], escape: false);
    }

    /** A slide with no picture is an empty box in the most visible spot. */
    public function test_products_without_images_never_become_slides(): void
    {
        Product::factory()->for($this->store)->create(['name' => 'من غير صورة']);

        // No product has an image, so there is nothing to build a hero from and
        // the slider must not render an empty frame in the most visible spot.
        $this->get($this->url())
            ->assertOk()
            ->assertDontSee('data-hero', escape: false);
    }

    // ── Deals & countdown ───────────────────────────────────────────────────

    public function test_live_discounts_appear_in_the_deals_section(): void
    {
        $this->product([
            'name' => 'عرض شغّال',
            'price' => 80,
            'compare_at_price' => 160,
            'sale_ends_at' => now()->addDays(2),
        ]);

        $this->get($this->url())
            ->assertOk()
            ->assertSee('عروض النهارده')
            ->assertSee('عرض شغّال');
    }

    /**
     * The countdown must never point at a moment that has passed — a timer
     * showing zeros is worse than no timer.
     */
    public function test_an_expired_sale_is_dropped_from_the_deals_section(): void
    {
        $this->product([
            'name' => 'عرض خلص',
            'price' => 80,
            'compare_at_price' => 160,
            'sale_ends_at' => now()->subHour(),
        ]);

        // The sale is over, so the deals block has nothing to list and its
        // heading must not appear at all.
        $this->get($this->url())
            ->assertOk()
            ->assertDontSee('عروض النهارده', escape: false);
    }

    public function test_a_discount_with_no_deadline_still_counts_as_a_deal(): void
    {
        $this->product(['price' => 80, 'compare_at_price' => 160, 'sale_ends_at' => null]);

        $this->get($this->url())
            ->assertOk()
            ->assertSee('عروض النهارده', escape: false);
    }

    public function test_the_countdown_uses_the_soonest_deadline(): void
    {
        $soon = now()->addDay()->startOfSecond();

        $this->product(['price' => 80, 'compare_at_price' => 160, 'sale_ends_at' => now()->addDays(5)]);
        $this->product(['price' => 70, 'compare_at_price' => 140, 'sale_ends_at' => $soon]);

        $this->get($this->url())
            ->assertOk()
            ->assertSee($soon->toIso8601String());
    }

    // ── Search ──────────────────────────────────────────────────────────────

    public function test_search_matches_on_name(): void
    {
        $this->product(['name' => 'قميص قطن']);
        $this->product(['name' => 'حذاء رياضي']);

        $this->get($this->url('/search?q=' . urlencode('قميص')))
            ->assertOk()
            ->assertSee('قميص قطن')
            ->assertDontSee('حذاء رياضي');
    }

    public function test_search_matches_on_sku(): void
    {
        $this->product(['name' => 'قميص قطن', 'sku' => 'SHIRT-01']);

        $this->get($this->url('/search?q=SHIRT-01'))
            ->assertOk()
            ->assertSee('قميص قطن');
    }

    /** A blank query should prompt, not dump the whole catalogue. */
    public function test_an_empty_query_returns_nothing(): void
    {
        $this->product(['name' => 'قميص قطن']);

        $this->get($this->url('/search?q='))
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 0)
            ->assertDontSee('قميص قطن');
    }

    public function test_a_search_with_no_results_offers_categories_instead(): void
    {
        Category::create([
            'store_id' => $this->store->id, 'name' => 'رجالي', 'slug' => 'men', 'is_active' => true,
        ])->products()->attach($this->product(['name' => 'قميص'])->id);

        $this->get($this->url('/search?q=zzzzz'))
            ->assertOk()
            ->assertSee('مفيش نتائج')
            ->assertSee('رجالي');
    }

    /** Thin, endless and duplicated — search pages must stay out of the index. */
    public function test_search_pages_are_not_indexed(): void
    {
        $this->get($this->url('/search?q=x'))
            ->assertOk()
            ->assertSee('noindex', false);
    }

    public function test_a_draft_product_never_shows_in_search(): void
    {
        $this->product(['name' => 'مسودة', 'status' => Product::STATUS_DRAFT]);

        $this->get($this->url('/search?q=' . urlencode('مسودة')))
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 0);
    }

    // ── Pagination ──────────────────────────────────────────────────────────

    public function test_a_long_catalogue_is_paginated(): void
    {
        Product::factory()->count(30)->for($this->store)->create();

        $this->get($this->url())
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->hasPages())
            ->assertSee('تنقّل بين الصفحات');
    }

    public function test_the_second_page_shows_the_rest(): void
    {
        Product::factory()->count(30)->for($this->store)->create();

        $this->get($this->url('/?page=2'))
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->count() === 6);
    }

    public function test_search_pagination_keeps_the_query(): void
    {
        Product::factory()->count(30)->for($this->store)->create(['name' => 'قميص قطن']);

        $this->get($this->url('/search?q=' . urlencode('قميص')))
            ->assertOk()
            ->assertSee('q=' . urlencode('قميص'), false);
    }
}

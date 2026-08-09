<?php

namespace Tests\Feature\Builder;

use App\Models\Product;
use App\Models\Store;
use App\Models\StorePage;
use App\Models\User;
use App\Services\Builder\PageBuilder;
use App\Services\Builder\PreviewToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The builder's two promises.
 *
 *  1. A store that never opens it is unchanged — every existing shop keeps
 *     rendering what it rendered before this feature shipped.
 *  2. Nothing a merchant does in it reaches a customer until they publish.
 *
 * Everything else here is the sanitiser, which is the only thing standing
 * between merchant-authored JSON and a rendered page.
 */
class StoreBuilderTest extends TestCase
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

    private function storefront(string $path = '/'): string
    {
        return $this->store->canonicalUrl() . $path;
    }

    private function builder(): PageBuilder
    {
        return app(PageBuilder::class);
    }

    // ── Defaults ────────────────────────────────────────────────────────

    public function test_a_store_that_never_opened_the_builder_gets_the_platform_layout(): void
    {
        Product::factory()->for($this->store)->create(['name' => 'قميص قطن']);

        $this->assertDatabaseCount('store_pages', 0);

        $this->get($this->storefront())
            ->assertOk()
            ->assertSee('قميص قطن', escape: false);
    }

    public function test_the_default_home_layout_matches_what_the_page_used_to_render(): void
    {
        $types = array_column($this->builder()->defaults('home'), 'type');

        $this->assertSame(['hero', 'deals', 'trust_bar', 'categories', 'product_grid'], $types);
    }

    // ── Draft vs live ───────────────────────────────────────────────────

    public function test_a_saved_draft_does_not_touch_the_live_storefront(): void
    {
        $this->actingAs($this->merchant)->put('/builder/home', [
            'sections' => [
                ['id' => 'aaaaaaaaaaaa', 'type' => 'rich_text', 'visible' => true,
                    'settings' => ['heading' => 'مسودة مش منشورة', 'body' => '', 'align' => 'right', 'width' => 'narrow']],
            ],
        ])->assertRedirect();

        // Saved as a draft…
        $this->assertNotNull(StorePage::first()->draft_sections);
        // …and the shop customers see is untouched.
        $this->assertNull(StorePage::first()->published_sections);
        $this->get($this->storefront())->assertOk()->assertDontSee('مسودة مش منشورة', escape: false);
    }

    public function test_publishing_puts_the_draft_live(): void
    {
        $this->actingAs($this->merchant)->put('/builder/home', [
            'sections' => [
                ['id' => 'aaaaaaaaaaaa', 'type' => 'rich_text', 'visible' => true,
                    'settings' => ['heading' => 'أهلاً بيك', 'body' => '', 'align' => 'right', 'width' => 'narrow']],
            ],
        ]);

        $this->actingAs($this->merchant)->post('/builder/home/publish')->assertRedirect();

        $this->get($this->storefront())->assertOk()->assertSee('أهلاً بيك', escape: false);
    }

    public function test_discarding_goes_back_to_what_is_published(): void
    {
        $this->actingAs($this->merchant)->put('/builder/home', [
            'sections' => [['id' => 'aaaaaaaaaaaa', 'type' => 'rich_text', 'visible' => true,
                'settings' => ['heading' => 'النسخة المنشورة', 'body' => '', 'align' => 'right', 'width' => 'narrow']]],
        ]);
        $this->actingAs($this->merchant)->post('/builder/home/publish');

        $this->actingAs($this->merchant)->put('/builder/home', [
            'sections' => [['id' => 'bbbbbbbbbbbb', 'type' => 'rich_text', 'visible' => true,
                'settings' => ['heading' => 'تجربة هرجع عنها', 'body' => '', 'align' => 'right', 'width' => 'narrow']]],
        ]);

        $this->actingAs($this->merchant)->post('/builder/home/discard');

        $draft = $this->builder()->draft($this->store->fresh(), 'home');
        $this->assertSame('النسخة المنشورة', $draft[0]['settings']['heading']);
    }

    // ── Preview ─────────────────────────────────────────────────────────

    public function test_a_preview_token_shows_the_draft(): void
    {
        $this->actingAs($this->merchant)->put('/builder/home', [
            'sections' => [['id' => 'aaaaaaaaaaaa', 'type' => 'rich_text', 'visible' => true,
                'settings' => ['heading' => 'شغل تحت التنفيذ', 'body' => '', 'align' => 'right', 'width' => 'narrow']]],
        ]);

        $token = app(PreviewToken::class)->issue($this->store);

        $this->get($this->storefront('/?_preview=' . $token))
            ->assertOk()
            ->assertSee('شغل تحت التنفيذ', escape: false)
            // A draft must never be cacheable or indexable.
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_a_token_for_another_store_does_not_unlock_this_one(): void
    {
        $this->actingAs($this->merchant)->put('/builder/home', [
            'sections' => [['id' => 'aaaaaaaaaaaa', 'type' => 'rich_text', 'visible' => true,
                'settings' => ['heading' => 'سر تجاري', 'body' => '', 'align' => 'right', 'width' => 'narrow']]],
        ]);

        // A competitor mints a token for their own shop and tries it here.
        $other = Store::factory()->create();
        $token = app(PreviewToken::class)->issue($other);

        $this->get($this->storefront('/?_preview=' . $token))
            ->assertOk()
            ->assertDontSee('سر تجاري', escape: false);
    }

    public function test_an_invented_token_is_simply_ignored(): void
    {
        $this->get($this->storefront('/?_preview=' . str_repeat('a', 40)))->assertOk();
    }

    // ── Sanitiser ───────────────────────────────────────────────────────

    public function test_unknown_fields_and_unknown_sections_never_survive_a_save(): void
    {
        $this->actingAs($this->merchant)->put('/builder/home', [
            'sections' => [
                ['id' => 'aaaaaaaaaaaa', 'type' => 'rich_text', 'visible' => true, 'settings' => [
                    'heading' => 'عنوان',
                    'body' => '',
                    'align' => 'right',
                    'width' => 'narrow',
                    'evil' => '<script>alert(1)</script>',
                ]],
                ['id' => 'bbbbbbbbbbbb', 'type' => 'not_a_real_section', 'visible' => true, 'settings' => []],
                // Right type, wrong page: a buy form has no product behind it here.
                ['id' => 'cccccccccccc', 'type' => 'product_main', 'visible' => true, 'settings' => []],
            ],
        ]);

        $draft = $this->builder()->draft($this->store->fresh(), 'home');

        $this->assertCount(1, $draft);
        $this->assertSame('rich_text', $draft[0]['type']);
        $this->assertArrayNotHasKey('evil', $draft[0]['settings']);
    }

    public function test_a_javascript_link_is_stripped(): void
    {
        $this->actingAs($this->merchant)->put('/builder/home', [
            'sections' => [['id' => 'aaaaaaaaaaaa', 'type' => 'banner', 'visible' => true, 'settings' => [
                'layout' => 'full',
                'items' => [['image' => 'builder/1/x.jpg', 'headline' => 'اضغط', 'sub' => '',
                    'button_text' => '', 'link' => 'javascript:alert(1)']],
            ]]],
        ]);

        $draft = $this->builder()->draft($this->store->fresh(), 'home');

        $this->assertSame('', $draft[0]['settings']['items'][0]['link']);
    }

    public function test_an_image_path_outside_the_builder_folder_is_dropped(): void
    {
        $this->actingAs($this->merchant)->put('/builder/home', [
            'sections' => [['id' => 'aaaaaaaaaaaa', 'type' => 'banner', 'visible' => true, 'settings' => [
                'layout' => 'full',
                'items' => [['image' => '../../.env', 'headline' => '', 'sub' => '', 'button_text' => '', 'link' => '']],
            ]]],
        ]);

        $draft = $this->builder()->draft($this->store->fresh(), 'home');

        $this->assertNull($draft[0]['settings']['items'][0]['image']);
    }

    public function test_a_product_from_another_store_cannot_be_featured(): void
    {
        $mine = Product::factory()->for($this->store)->create();
        $theirs = Product::factory()->for(Store::factory()->create())->create();

        $this->actingAs($this->merchant)->put('/builder/home', [
            'sections' => [['id' => 'aaaaaaaaaaaa', 'type' => 'featured_products', 'visible' => true, 'settings' => [
                'title' => 'مختارات',
                'products' => [$theirs->id, $mine->id],
                'columns' => '4',
            ]]],
        ]);

        $draft = $this->builder()->draft($this->store->fresh(), 'home');

        $this->assertSame([$mine->id], $draft[0]['settings']['products']);
    }

    public function test_a_locked_section_is_put_back_if_it_goes_missing(): void
    {
        // A product page with no buy form is a product page that cannot sell.
        $this->actingAs($this->merchant)->put('/builder/product', ['sections' => []]);

        $types = array_column($this->builder()->draft($this->store->fresh(), 'product'), 'type');

        $this->assertContains('product_main', $types);
    }

    public function test_a_locked_section_cannot_be_hidden(): void
    {
        $this->actingAs($this->merchant)->put('/builder/product', [
            'sections' => [['id' => 'aaaaaaaaaaaa', 'type' => 'product_main', 'visible' => false, 'settings' => []]],
        ]);

        $draft = $this->builder()->draft($this->store->fresh(), 'product');

        $this->assertTrue($draft[0]['visible']);
    }

    public function test_a_number_outside_its_range_is_clamped_not_rejected(): void
    {
        $this->actingAs($this->merchant)->put('/builder/home', [
            'sections' => [['id' => 'aaaaaaaaaaaa', 'type' => 'deals', 'visible' => true,
                'settings' => ['title' => 'عروض', 'limit' => 9999]]],
        ]);

        $draft = $this->builder()->draft($this->store->fresh(), 'home');

        $this->assertSame(20, $draft[0]['settings']['limit']);
    }

    // ── Access ──────────────────────────────────────────────────────────

    /**
     * The very first visit, before a single `store_pages` row exists.
     *
     * This is the state every store is in the first time the merchant clicks
     * "تصميم المتجر", so it is the one path that absolutely cannot 500 — and
     * it did, because the page switcher indexed a Collection by a key that was
     * not there yet.
     */
    public function test_the_builder_opens_for_a_store_with_no_saved_pages(): void
    {
        $this->assertDatabaseCount('store_pages', 0);

        $this->actingAs($this->merchant)->get('/builder')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('builder/Index')
                ->where('page', 'home')
                ->has('sections', 5)
                ->has('pages', 5)
                ->where('pages.0.dirty', false)
                ->where('pages.0.published', null));
    }

    public function test_every_page_of_the_builder_opens(): void
    {
        foreach (['home', 'product', 'category', 'header', 'footer'] as $page) {
            $this->actingAs($this->merchant)->get("/builder/{$page}")->assertOk();
        }
    }

    public function test_the_builder_needs_a_login(): void
    {
        $this->get('/builder')->assertRedirect('/login');
        $this->put('/builder/home', ['sections' => []])->assertRedirect('/login');
    }

    public function test_a_merchant_only_ever_edits_their_own_store(): void
    {
        $other = User::factory()->create();
        Store::factory()->for($other)->create();

        $this->actingAs($other)->put('/builder/home', [
            'sections' => [['id' => 'aaaaaaaaaaaa', 'type' => 'rich_text', 'visible' => true,
                'settings' => ['heading' => 'مش بتاعي', 'body' => '', 'align' => 'right', 'width' => 'narrow']]],
        ]);

        // The route carries no store id at all — it always resolves the signed-in
        // merchant's own shop, so there is nothing to tamper with.
        $this->assertSame([], $this->builder()->draft($this->store->fresh(), 'home') === []
            ? []
            : array_filter(
                $this->builder()->draft($this->store->fresh(), 'home'),
                fn ($s) => ($s['settings']['heading'] ?? null) === 'مش بتاعي',
            ));
    }

    public function test_an_unknown_page_is_a_404(): void
    {
        $this->actingAs($this->merchant)->get('/builder/nonsense')->assertNotFound();
    }
}

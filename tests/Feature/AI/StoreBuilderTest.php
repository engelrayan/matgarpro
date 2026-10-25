<?php

namespace Tests\Feature\AI;

use App\Models\AiCall;
use App\Models\AiGeneration;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StorePage;
use App\Models\User;
use App\Services\AI\AiException;
use App\Services\AI\AiFeature;
use App\Services\Builder\PageBuilder;
use App\Services\Builder\PreviewToken;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * "Tell us about your shop and we'll set it up": a description in, a plan
 * out, and — only on the merchant's click — builder drafts, empty categories
 * and, if ticked, the theme.
 *
 * The promise under test is the one on the screen: nothing a customer sees
 * changes until the merchant publishes, except the look, which they ticked.
 */
class StoreBuilderTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        config([
            'services.anthropic.key' => 'sk-ant-test-key',
            'ai.daily_cost_cap_usd' => 1.00,
            'ai.features.store_builder.daily_limit' => 5,
        ]);

        $this->merchant = User::factory()->create();
        $this->store = Store::factory()->for($this->merchant)->create(['name' => 'ستايل', 'settings' => ['theme' => 'jade']]);
    }

    /** @param array<string,mixed> $overrides */
    private function plan(array $overrides = []): array
    {
        return array_merge([
            'theme' => 'coral',
            'theme_reason' => 'البرتقالي الدافي بيليق على الهدوم الشبابي.',
            'about' => 'ستايل متجر هدوم رجالي كاجوال بخامات قطن مصري.',
            'announcement' => 'الدفع عند الاستلام · شحن لكل المحافظات',
            'categories' => [
                ['name' => 'تيشيرتات', 'description' => 'تيشيرتات قطن مريحة.'],
                ['name' => 'بناطيل', 'description' => 'بناطيل جينز وكاجوال.'],
                ['name' => 'جواكت', 'description' => 'جواكت للشتا.'],
            ],
            'trust_items' => [
                ['icon' => 'wallet', 'title' => 'الدفع عند الاستلام', 'body' => 'ادفع لما الأوردر يوصلك'],
                ['icon' => 'truck', 'title' => 'شحن لكل المحافظات', 'body' => 'من ٢ لـ ٤ أيام'],
            ],
            'titles' => ['deals' => 'عروض الأسبوع', 'categories' => 'اختار القسم', 'products' => 'وصل جديد'],
            'story_heading' => 'إحنا مين؟',
            'story_html' => '<p>بنعمل هدوم <strong>مريحة</strong> للبس كل يوم.</p>',
            'faq' => [
                ['question' => 'أدفع إزاي؟', 'answer' => 'كاش للمندوب لما الأوردر يوصلك.'],
                ['question' => 'الشحن بياخد قد إيه؟', 'answer' => 'من ٢ لـ ٤ أيام لكل المحافظات.'],
            ],
            'heads_up' => 'ماقلتش حاجة عن الاستبدال، فسيبناه برّه.',
        ], $overrides);
    }

    /** @param array<string,mixed> $plan */
    private function claudeReplies(array $plan, string $stopReason = 'end_turn'): void
    {
        $message = $this->claudeMessage($plan, $stopReason);

        // A fresh response per request; see ProductWriterTest::claudeReplies.
        Http::fake(['api.anthropic.com/*' => fn () => Http::response($message)]);
    }

    /**
     * @param  array<string,mixed>  $plan
     * @return array<string,mixed>
     */
    private function claudeMessage(array $plan, string $stopReason = 'end_turn'): array
    {
        return [
            'id' => 'msg_store_plan',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-haiku-5-5',
            'content' => [['type' => 'text', 'text' => json_encode($plan, JSON_UNESCAPED_UNICODE)]],
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 2500, 'output_tokens' => 2200],
        ];
    }

    /** @param array<string,mixed> $answers */
    private function describe(array $answers = []): TestResponse
    {
        return $this->actingAs($this->merchant)->postJson('/ai/store-plans', $answers + [
            'business' => 'هدوم رجالي كاجوال، تيشيرتات وبناطيل وجواكت، قطن مصري',
            'audience' => 'شباب من ١٨ لـ ٣٥',
            'mood' => 'شبابي ومبهج',
            'shipping' => 'بنشحن لكل المحافظات في ٢ لـ ٤ أيام',
            'social' => ['instagram' => 'https://instagram.com/style.eg', 'whatsapp' => '01012345678'],
        ]);
    }

    /** @param array<string,mixed> $choices */
    private function apply(string $id, array $choices = []): TestResponse
    {
        return $this->actingAs($this->merchant)->post("/ai/store-plans/{$id}/apply", $choices + [
            'apply_theme' => true,
            'categories' => ['تيشيرتات', 'بناطيل', 'جواكت'],
        ]);
    }

    /** @return array<int,string> */
    private function draftTypes(string $page): array
    {
        return array_column(app(PageBuilder::class)->draft($this->store, $page), 'type');
    }

    /** @return array<string,mixed> */
    private function draftSection(string $page, string $type): array
    {
        return collect(app(PageBuilder::class)->draft($this->store, $page))->firstWhere('type', $type)['settings'];
    }

    // ── The plan ──────────────────────────────────────────────────────────

    public function test_the_builder_page_opens(): void
    {
        $this->actingAs($this->merchant)
            ->get('/ai/store-builder')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ai/StoreBuilder')
                ->where('currentTheme', 'jade')
                ->where('triesLeft', 2)
                ->has('themes'));
    }

    public function test_a_description_comes_back_as_a_plan_and_nothing_changes(): void
    {
        $this->claudeReplies($this->plan());

        $this->describe()
            ->assertStatus(202)
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('draft.theme', 'coral')
            ->assertJsonPath('draft.categories.0.name', 'تيشيرتات')
            ->assertJsonPath('draft.titles.products', 'وصل جديد')
            ->assertJsonPath('draft.faq.1.question', 'الشحن بياخد قد إيه؟');

        // A plan only: no category, no page, no theme yet.
        $this->assertSame(0, $this->store->categories()->count());
        $this->assertSame(0, StorePage::count());
        $this->assertSame('jade', $this->store->fresh()->settings['theme']);
    }

    public function test_the_model_is_told_the_shop_the_themes_and_the_existing_categories(): void
    {
        Category::create(['store_id' => $this->store->id, 'name' => 'شنط', 'slug' => 'bags']);
        $this->claudeReplies($this->plan());

        $this->describe();

        Http::assertSent(function (HttpRequest $request) {
            $body = $request->data();
            $text = $body['messages'][0]['content'][0]['text'];
            $themeEnum = $body['output_config']['format']['schema']['properties']['theme']['enum'];

            // Haiku, without fallbacks: it has none, and asking is a 400.
            return $body['model'] === 'claude-haiku-5-5'
                && ! array_key_exists('fallbacks', $body)
                && str_contains($text, 'هدوم رجالي كاجوال')
                && str_contains($text, 'بنشحن لكل المحافظات')
                && str_contains($text, 'شنط')
                && str_contains($text, 'coral: ')
                && in_array('coral', $themeEnum, true)
                // The links go to the footer, not to the model.
                && ! str_contains($text, 'instagram.com');
        });
    }

    public function test_a_plan_is_held_to_what_the_builder_will_accept(): void
    {
        Category::create(['store_id' => $this->store->id, 'name' => 'جواكت', 'slug' => 'jackets']);

        $this->claudeReplies($this->plan([
            'theme' => 'no-such-theme',
            'story_html' => '<p onclick="steal()">حلو</p><script>alert(1)</script>',
            'categories' => [
                ['name' => 'جواكت', 'description' => 'مكرر'],
                ['name' => 'تيشيرتات', 'description' => ''],
                ['name' => 'تيشيرتات ', 'description' => 'مكرر بمسافة'],
            ],
            'trust_items' => [['icon' => 'rocket', 'title' => 'سريع', 'body' => '']],
        ]));

        $draft = $this->describe()->json('draft');

        // An unknown theme falls back to the one the store already has.
        $this->assertSame('jade', $draft['theme']);
        $this->assertSame(['تيشيرتات'], array_column($draft['categories'], 'name'));
        $this->assertSame('check', $draft['trust_items'][0]['icon']);
        $this->assertStringNotContainsString('<script', $draft['story']['body']);
        $this->assertStringNotContainsString('onclick', $draft['story']['body']);
    }

    public function test_a_store_gets_two_plans_ever_not_two_a_day(): void
    {
        $this->claudeReplies($this->plan());

        $this->describe()->assertStatus(202);
        $this->describe()->assertStatus(202);
        $this->describe()->assertStatus(429)->assertJson(['message' => AiException::usedUp()->getMessage()]);

        // A new day does not hand out new ones.
        $this->travel(3)->days();
        $this->describe()->assertStatus(429);

        Http::assertSentCount(2);
        $this->assertSame(['store_builder'], AiCall::pluck('feature')->unique()->values()->all());

        $this->actingAs($this->merchant)
            ->get('/ai/store-builder')
            ->assertInertia(fn ($page) => $page->where('triesLeft', 0));
    }

    public function test_a_failed_run_does_not_use_up_a_try(): void
    {
        AiCall::create([
            'store_id' => $this->store->id,
            'feature' => 'store_builder',
            'model' => 'claude-haiku-5-5',
            'status' => AiCall::STATUS_FAILED,
        ]);

        $this->actingAs($this->merchant)
            ->get('/ai/store-builder')
            ->assertInertia(fn ($page) => $page->where('triesLeft', 2));
    }

    public function test_switching_back_to_sonnet_brings_fallbacks_back_by_itself(): void
    {
        config(['ai.features.store_builder.model' => 'claude-sonnet-5-5']);
        $this->claudeReplies($this->plan());

        $this->describe()->assertStatus(202);

        Http::assertSent(fn (HttpRequest $request) => $request->data()['model'] === 'claude-sonnet-5-5'
            && ($request->data()['fallbacks'] ?? null) === 'default');
    }

    public function test_opening_store_after_store_from_one_network_runs_out(): void
    {
        Http::fake();

        // Twenty merchants on one address, each asking once. An empty
        // description is refused after the throttle has counted it, so this
        // costs no calls and still uses up the network's day.
        foreach (range(1, 20) as $_) {
            $merchant = User::factory()->create();
            Store::factory()->for($merchant)->create();
            $this->actingAs($merchant)->postJson('/ai/store-plans', ['business' => ''])->assertStatus(422);
        }

        $merchant = User::factory()->create();
        Store::factory()->for($merchant)->create();
        $this->actingAs($merchant)->postJson('/ai/store-plans', ['business' => 'عطور'])
            ->assertStatus(429)
            ->assertJsonFragment(['message' => 'المتاجر اللي اتجهّزت من نفس المكان النهارده كتير. جرّب تاني بكرة.']);

        Http::assertNothingSent();
    }

    public function test_the_product_writer_keeps_its_daily_limit(): void
    {
        // The lifetime cap belongs to the store builder alone.
        $this->assertNull(AiFeature::ProductWriter->totalLimit());
        $this->assertSame(2, AiFeature::StoreBuilder->totalLimit());
    }

    public function test_a_description_is_required(): void
    {
        Http::fake();

        $this->describe(['business' => ''])->assertStatus(422)->assertJsonValidationErrors('business');
        $this->describe(['social' => ['facebook' => 'javascript:alert(1)']])->assertStatus(422);

        Http::assertNothingSent();
    }

    // ── Applying it ───────────────────────────────────────────────────────

    public function test_applying_puts_home_header_and_footer_into_the_builder_as_drafts(): void
    {
        $this->claudeReplies($this->plan());
        $id = $this->describe()->json('id');

        // Straight to the shop itself, not to a list of what was drafted.
        $this->apply($id)->assertRedirect("/ai/store-plans/{$id}/preview");

        $this->assertSame(
            ['hero', 'deals', 'trust_bar', 'categories', 'product_grid', 'rich_text', 'faq'],
            $this->draftTypes('home'),
        );
        $this->assertSame(['announcement_bar', 'header_nav'], $this->draftTypes('header'));
        $this->assertSame(['footer_main', 'footer_social', 'footer_note'], $this->draftTypes('footer'));

        $this->assertSame('وصل جديد', $this->draftSection('home', 'product_grid')['title']);
        $this->assertSame('عروض الأسبوع', $this->draftSection('home', 'deals')['title']);
        $this->assertSame('شحن لكل المحافظات', $this->draftSection('home', 'trust_bar')['items'][1]['title']);
        $this->assertSame('أدفع إزاي؟', $this->draftSection('home', 'faq')['items'][0]['question']);
        $this->assertSame('الدفع عند الاستلام · شحن لكل المحافظات', $this->draftSection('header', 'announcement_bar')['text']);
        $this->assertSame('ستايل متجر هدوم رجالي كاجوال بخامات قطن مصري.', $this->draftSection('footer', 'footer_main')['about']);
        $this->assertSame([
            ['platform' => 'instagram', 'url' => 'https://instagram.com/style.eg'],
            ['platform' => 'whatsapp', 'url' => 'https://wa.me/201012345678'],
        ], $this->draftSection('footer', 'footer_social')['items']);

        // Drafts, not live: nothing has been published.
        $this->assertSame(0, StorePage::whereNotNull('published_sections')->count());
    }

    public function test_what_customers_see_does_not_change_until_the_merchant_publishes(): void
    {
        $builder = app(PageBuilder::class);
        $builder->saveDraft($this->store, 'home', [['type' => 'hero', 'settings' => []]]);
        $builder->publish($this->store, 'home');
        $live = $builder->published($this->store, 'home');

        $this->claudeReplies($this->plan());
        $this->apply($this->describe()->json('id'));

        $this->assertSame($live, $builder->published($this->store, 'home'));
        $this->assertNotSame($live, $builder->draft($this->store, 'home'));
    }

    /**
     * The end of the chain: the real storefront, rendering the drafts the plan
     * became. The preview shows the new words; the live shop does not.
     */
    public function test_the_drafted_store_renders_in_the_preview_and_not_on_the_live_shop(): void
    {
        $this->claudeReplies($this->plan());
        $this->apply($this->describe()->json('id'));

        $token = app(PreviewToken::class)->issue($this->store);

        $this->get($this->store->canonicalUrl() . '/?_preview=' . $token)
            ->assertOk()
            ->assertSee('الدفع عند الاستلام · شحن لكل المحافظات', escape: false)
            ->assertSee('أدفع إزاي؟', escape: false)
            ->assertSee('ستايل متجر هدوم رجالي كاجوال', escape: false)
            ->assertSee('instagram.com/style.eg', escape: false);

        $this->get($this->store->canonicalUrl() . '/')
            ->assertOk()
            ->assertDontSee('أدفع إزاي؟', escape: false);
    }

    // ── Starter products and the preview ──────────────────────────────────

    /** @return array<int,array<string,mixed>> */
    private function starters(): array
    {
        return [
            ['name' => 'تيشيرت قطن أبيض', 'price' => 350, 'compare_at_price' => 450, 'description' => 'تيشيرت قطن بياقة دايرية.', 'category' => 'تيشيرتات', 'photo_query' => 'white cotton t-shirt'],
            ['name' => 'جاكت جينز', 'price' => 900, 'compare_at_price' => 0, 'description' => 'جاكت جينز أزرق.', 'category' => 'جواكت', 'photo_query' => 'denim jacket'],
            ['name' => 'حاجة غريبة', 'price' => 120, 'compare_at_price' => 100, 'description' => '', 'category' => 'مش موجود', 'photo_query' => 'quantum gizmo عربي'],
        ];
    }

    public function test_the_plan_comes_with_starter_products_held_to_the_product_form(): void
    {
        Storage::fake('public');
        $this->claudeReplies($this->plan(['products' => $this->starters()]));

        $products = $this->describe()->json('draft.products');

        $this->assertSame(['تيشيرت قطن أبيض', 'جاكت جينز', 'حاجة غريبة'], array_column($products, 'name'));
        $this->assertSame([450, null, null], array_column($products, 'compare'), 'a "was" price at or under the price is no discount');
        $this->assertSame(['تيشيرتات', 'جواكت', ''], array_column($products, 'category'));
        $this->assertSame('quantum gizmo', $products[2]['photo_query'], 'only plain words go to the photo search');
    }

    public function test_without_a_pexels_key_photos_come_from_the_library_or_not_at_all(): void
    {
        Storage::fake('public');
        config(['services.pexels.key' => null]);
        $this->claudeReplies($this->plan(['products' => $this->starters()]));

        $images = array_column($this->describe()->json('draft.products'), 'image');

        // A t-shirt and a jacket match the fashion library; a "gizmo" matches
        // nothing and gets no photo rather than a wrong one.
        $this->assertStringEndsWith('/0.jpg', $images[0]);
        $this->assertStringEndsWith('/1.jpg', $images[1]);
        $this->assertNull($images[2]);
        Storage::disk('public')->assertExists($images[0]);
        Http::assertSentCount(1);
    }

    public function test_with_a_pexels_key_each_product_gets_its_own_photo(): void
    {
        Storage::fake('public');
        config(['services.pexels.key' => 'pexels-test-key']);

        $message = $this->claudeMessage($this->plan(['products' => array_slice($this->starters(), 0, 2)]));
        Http::fake([
            'api.anthropic.com/*' => Http::response($message),
            'api.pexels.com/*' => Http::response(['photos' => [
                ['id' => 11, 'src' => ['large' => 'https://images.pexels.com/photos/11/a.jpeg']],
                ['id' => 12, 'src' => ['large' => 'https://images.pexels.com/photos/12/b.jpeg']],
            ]]),
            'images.pexels.com/*' => Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $images = array_column($this->describe()->json('draft.products'), 'image');

        Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), 'https://api.pexels.com/v1/search')
            && $r->hasHeader('Authorization', 'pexels-test-key')
            && $r['query'] === 'white cotton t-shirt');
        // Two products, two different photos, even though the search returned the same list.
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://images.pexels.com/photos/11/a.jpeg');
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://images.pexels.com/photos/12/b.jpeg');
        $this->assertSame('jpeg-bytes', Storage::disk('public')->get($images[1]));
    }

    public function test_pixabay_comes_first_and_its_photo_is_downloaded_not_linked(): void
    {
        Storage::fake('public');
        config(['services.pixabay.key' => 'pixabay-test-key', 'services.pexels.key' => 'pexels-test-key']);

        $message = $this->claudeMessage($this->plan(['products' => array_slice($this->starters(), 0, 2)]));
        Http::fake([
            'api.anthropic.com/*' => Http::response($message),
            'pixabay.com/api/*' => Http::response(['hits' => [
                ['id' => 21, 'largeImageURL' => 'https://pixabay.com/get/a_1280.jpg'],
                ['id' => 22, 'largeImageURL' => 'https://pixabay.com/get/b_1280.jpg'],
            ]]),
            'pixabay.com/get/*' => Http::response('pixabay-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $images = array_column($this->describe()->json('draft.products'), 'image');

        Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), 'https://pixabay.com/api/')
            && $r['key'] === 'pixabay-test-key' && $r['q'] === 'denim jacket' && $r['safesearch'] === 'true');
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://pixabay.com/get/b_1280.jpg');
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'pexels.com'));
        // Our own copy: Pixabay forbids hotlinking its URLs.
        $this->assertSame('pixabay-bytes', Storage::disk('public')->get($images[0]));
    }

    public function test_starter_products_are_drafts_the_preview_shows_and_customers_never_see(): void
    {
        Storage::fake('public');
        $this->claudeReplies($this->plan(['products' => $this->starters()]));
        $id = $this->describe()->json('id');
        // As the plan screen sends it: no theme switch until نشر.
        $this->apply($id, ['apply_theme' => false]);

        $starters = $this->store->products()->get();
        $this->assertCount(3, $starters);
        $this->assertSame([Product::STATUS_DRAFT], $starters->pluck('status')->unique()->values()->all());
        $this->assertSame($starters->pluck('id')->all(), $this->store->fresh()->settings['ai_samples']);
        $this->assertSame('تيشيرتات', $starters->firstWhere('name', 'تيشيرت قطن أبيض')->categories()->first()->name);
        // Every starter has a picture: the fetched photo, or a drawn one.
        $this->assertTrue($starters->every(fn (Product $p) => $p->images()->exists()));

        $token = app(PreviewToken::class)->issue($this->store, 'noir');

        $this->get($this->store->canonicalUrl() . '/?_preview=' . $token)
            ->assertOk()
            ->assertSee('تيشيرت قطن أبيض', escape: false)
            // The plan's theme, before the shop has been switched to it.
            ->assertSee(app(ThemeResolver::class)->fontUrl(app(ThemeResolver::class)->byKey('noir')));

        $this->get($this->store->canonicalUrl() . '/')
            ->assertOk()
            ->assertDontSee('تيشيرت قطن أبيض', escape: false);
        $this->assertSame('jade', $this->store->fresh()->settings['theme']);
    }

    public function test_writing_again_replaces_the_starters_that_are_still_drafts(): void
    {
        Storage::fake('public');
        config(['ai.features.store_builder.total_limit' => 3]);
        $this->claudeReplies($this->plan(['products' => $this->starters()]));
        $this->apply($this->describe()->json('id'));

        // The merchant has made one of them theirs.
        $kept = $this->store->products()->where('name', 'جاكت جينز')->first();
        $kept->update(['status' => Product::STATUS_ACTIVE]);

        $this->apply($this->describe()->json('id'));

        $this->assertSame(4, $this->store->products()->count());
        $this->assertTrue($kept->fresh()->exists);
        $this->assertSame(3, $this->store->products()->where('status', Product::STATUS_DRAFT)->count());
    }

    public function test_the_preview_page_shows_the_store_in_the_plans_theme(): void
    {
        Storage::fake('public');
        $this->claudeReplies($this->plan(['products' => $this->starters()]));
        $id = $this->describe()->json('id');

        // Not before it has been put in the store.
        $this->actingAs($this->merchant)->get("/ai/store-plans/{$id}/preview")->assertRedirect('/ai/store-builder');

        $this->apply($id);

        $page = $this->actingAs($this->merchant)->get("/ai/store-plans/{$id}/preview")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('ai/StorePreview')
                ->where('theme.key', 'coral')
                ->where('productCount', 3)
                ->where('published', false))
            ->viewData('page')['props'];

        parse_str((string) parse_url($page['previewUrl'], PHP_URL_QUERY), $query);
        $this->assertSame('coral', app(PreviewToken::class)->theme($query['_preview']));
        $this->assertSame($this->store->id, app(PreviewToken::class)->storeId($query['_preview']));
    }

    public function test_publishing_makes_the_pages_and_theme_live_but_not_the_starters(): void
    {
        Storage::fake('public');
        $this->claudeReplies($this->plan(['products' => $this->starters()]));
        $id = $this->describe()->json('id');
        $this->apply($id);

        $this->actingAs($this->merchant)->post("/ai/store-plans/{$id}/publish")->assertRedirect();

        $this->assertSame('coral', $this->store->fresh()->settings['theme']);
        $this->get($this->store->canonicalUrl() . '/')
            ->assertOk()
            ->assertSee('أدفع إزاي؟', escape: false)
            ->assertDontSee('تيشيرت قطن أبيض', escape: false);
        $this->assertSame(0, $this->store->products()->where('status', Product::STATUS_ACTIVE)->count());

        // Absolute: the requests above went to the shop's own hostname.
        $this->actingAs($this->merchant)->get("http://localhost/ai/store-plans/{$id}/preview")
            ->assertInertia(fn ($page) => $page->where('published', true));
    }

    public function test_the_trust_bar_never_falls_back_to_promises_the_merchant_did_not_make(): void
    {
        $this->claudeReplies($this->plan(['trust_items' => [], 'announcement' => '']));
        $this->apply($this->describe()->json('id'));

        $items = $this->draftSection('home', 'trust_bar')['items'];
        $titles = array_column($items, 'title');

        $this->assertSame(['الدفع عند الاستلام'], $titles);
        $this->assertSame('الدفع عند الاستلام', $this->draftSection('header', 'announcement_bar')['text']);
    }

    public function test_categories_are_the_merchants_final_list_and_start_empty(): void
    {
        Category::create(['store_id' => $this->store->id, 'name' => 'بناطيل', 'slug' => 'pants']);
        $this->claudeReplies($this->plan());

        $this->apply($this->describe()->json('id'), [
            // Removed one, added one, kept one the store already has.
            'categories' => ['تيشيرتات', 'بناطيل', 'شورتات'],
        ]);

        $names = $this->store->categories()->orderBy('sort_order')->pluck('name')->all();
        $this->assertSame(['بناطيل', 'تيشيرتات', 'شورتات'], $names);
        $this->assertSame('تيشيرتات قطن مريحة.', Category::where('name', 'تيشيرتات')->value('description'));
        $this->assertSame(0, Product::count());
    }

    public function test_the_theme_changes_only_when_ticked(): void
    {
        $this->claudeReplies($this->plan());

        $this->apply($this->describe()->json('id'), ['apply_theme' => false]);
        $this->assertSame('jade', $this->store->fresh()->settings['theme']);

        AiGeneration::query()->delete();
        $this->apply($this->describe()->json('id'), ['apply_theme' => true]);
        $this->assertSame('coral', $this->store->fresh()->settings['theme']);
    }

    public function test_a_plan_is_applied_once(): void
    {
        $this->claudeReplies($this->plan());
        $id = $this->describe()->json('id');

        $this->apply($id)->assertRedirect();
        $this->apply($id)->assertSessionHasErrors('plan');

        $this->assertSame(3, $this->store->categories()->count());
    }

    public function test_a_merchant_cannot_read_or_apply_another_stores_plan(): void
    {
        $this->claudeReplies($this->plan());
        $id = $this->describe()->json('id');

        $stranger = User::factory()->create();
        $theirs = Store::factory()->for($stranger)->create();

        $this->actingAs($stranger)->getJson("/ai/store-plans/{$id}")->assertNotFound();
        $this->actingAs($stranger)->post("/ai/store-plans/{$id}/apply", ['categories' => ['x']])->assertNotFound();

        $this->assertSame(0, $theirs->categories()->count());
        $this->assertSame(0, $this->store->categories()->count());
    }

    public function test_a_product_draft_cannot_be_applied_as_a_store_plan(): void
    {
        $generation = AiGeneration::create([
            'store_id' => $this->store->id, 'feature' => 'product_writer',
            'status' => AiGeneration::STATUS_DONE, 'result' => ['name' => 'x'],
        ]);

        $this->apply($generation->id)->assertNotFound();
    }

    public function test_an_unfinished_plan_cannot_be_applied(): void
    {
        $generation = AiGeneration::create([
            'store_id' => $this->store->id, 'feature' => 'store_builder', 'status' => AiGeneration::STATUS_RUNNING,
        ]);

        $this->apply($generation->id)->assertNotFound();
        $this->assertSame(0, StorePage::count());
    }
}

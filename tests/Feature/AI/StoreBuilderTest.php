<?php

namespace Tests\Feature\AI;

use App\Models\AiCall;
use App\Models\AiGeneration;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StorePage;
use App\Models\User;
use App\Services\Builder\PageBuilder;
use App\Services\Builder\PreviewToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
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
        $message = [
            'id' => 'msg_store_plan',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-sonnet-5-5',
            'content' => [['type' => 'text', 'text' => json_encode($plan, JSON_UNESCAPED_UNICODE)]],
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 2500, 'output_tokens' => 2200],
        ];

        // A fresh response per request; see ProductWriterTest::claudeReplies.
        Http::fake(['api.anthropic.com/*' => fn () => Http::response($message)]);
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
                ->where('triesLeft', 5)
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

            return $body['model'] === 'claude-sonnet-5-5'
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

    public function test_the_store_builder_has_its_own_daily_tries(): void
    {
        config(['ai.features.store_builder.daily_limit' => 1]);
        $this->claudeReplies($this->plan());

        $this->describe()->assertStatus(202);
        $this->describe()->assertStatus(429);

        Http::assertSentCount(1);
        $this->assertSame('store_builder', AiCall::sole()->feature);
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

        $this->apply($id)->assertRedirect('/builder/home');

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

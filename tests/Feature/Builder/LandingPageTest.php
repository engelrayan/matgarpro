<?php

namespace Tests\Feature\Builder;

use App\Models\AiGeneration;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreWalletTransaction;
use App\Models\User;
use App\Services\Billing\WalletService;
use App\Services\Builder\PageBuilder;
use App\Services\Builder\SectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A product's own landing page: designed in the builder, served on the
 * product's own URL, written by AI if the merchant wants — and paid for out of
 * the wallet only when it lands.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = User::factory()->create();
        $this->store = Store::factory()->for($this->merchant)->create(['slug' => 'mahmoud', 'status' => Store::STATUS_ACTIVE]);
        $this->product = Product::factory()->for($this->store)->create([
            'name' => 'كريستالة إزالة الشعر',
            'status' => Product::STATUS_ACTIVE,
            'price' => 194,
        ]);
    }

    private function key(?Product $product = null): string
    {
        return SectionRegistry::landingKey(($product ?? $this->product)->id);
    }

    private function url(): string
    {
        return $this->store->canonicalUrl() . '/p/' . $this->product->slug;
    }

    /** @param array<string,mixed> $hero */
    private function publishLanding(array $hero = []): void
    {
        $builder = app(PageBuilder::class);
        $sections = $builder->defaults($this->key());
        $sections[0]['settings'] = [...$sections[0]['settings'], ...$hero];

        $builder->saveDraft($this->store, $this->key(), $sections);
        $builder->publish($this->store, $this->key());
    }

    // ── Keys ────────────────────────────────────────────────────────────

    public function test_landing_keys_are_a_kind_of_page(): void
    {
        $this->assertSame('landing', SectionRegistry::kindOf('landing-42'));
        $this->assertSame(42, SectionRegistry::landingProductId('landing-42'));
        $this->assertNull(SectionRegistry::kindOf('landing-'));
        $this->assertNull(SectionRegistry::kindOf('landing-0'));
        $this->assertNull(SectionRegistry::kindOf('landing-1;drop'));
        $this->assertTrue(app(SectionRegistry::class)->allowedOn('landing_hero', 'landing-42'));
        $this->assertFalse(app(SectionRegistry::class)->allowedOn('landing_hero', 'home'));
    }

    // ── The storefront ──────────────────────────────────────────────────

    public function test_the_product_page_is_unchanged_until_a_landing_page_is_published(): void
    {
        app(PageBuilder::class)->saveDraft($this->store, $this->key(), app(PageBuilder::class)->defaults($this->key()));

        $this->get($this->url())->assertOk()->assertDontSee('data-section-type="landing_hero"', false);
    }

    public function test_a_published_landing_page_replaces_the_product_page_on_the_same_url(): void
    {
        $this->publishLanding(['headline' => 'بشرة ناعمة من غير وجع']);

        $this->get($this->url())
            ->assertOk()
            ->assertSee('بشرة ناعمة من غير وجع')
            ->assertSee('data-section-type="landing_hero"', false)
            // The form is still there — it is what the page is for.
            ->assertSee('id="orderForm"', false);
    }

    public function test_it_can_sit_under_the_usual_product_page_instead(): void
    {
        $this->publishLanding(['headline' => 'بشرة ناعمة من غير وجع']);
        $this->product->update(['settings' => ['landing_below_product' => true]]);

        $html = $this->get($this->url())->assertOk()->getContent();

        $this->assertStringContainsString('بشرة ناعمة من غير وجع', $html);
        // One form, not two: the landing page's own is left out.
        $this->assertSame(1, substr_count($html, 'id="orderForm"'));
        $this->assertLessThan(strpos($html, 'landing_hero'), strpos($html, 'product_main'), 'the product page comes first');
    }

    public function test_an_empty_landing_page_still_sells_with_the_products_own_name_and_image(): void
    {
        $this->publishLanding();

        $this->get($this->url())->assertOk()->assertSee('كريستالة إزالة الشعر')->assertSee('اطلب دلوقتي');
    }

    public function test_removing_it_brings_the_product_page_back(): void
    {
        $this->publishLanding(['headline' => 'بشرة ناعمة من غير وجع']);

        $this->actingAs($this->merchant)
            ->delete("http://localhost/products/{$this->product->id}/landing")
            ->assertRedirect();

        $this->get($this->url())->assertOk()->assertDontSee('بشرة ناعمة من غير وجع');
    }

    // ── Live orders ─────────────────────────────────────────────────────

    public function test_live_orders_come_from_real_orders_without_names(): void
    {
        foreach (['الجيزة', 'الإسكندرية', 'المنصورة'] as $place) {
            $order = Order::factory()->for($this->store)->create([
                'governorate' => $place, 'customer_name' => 'سارة عبد الله', 'customer_phone' => '01223344556',
            ]);
            $order->items()->create(['product_id' => $this->product->id, 'name' => 'x', 'unit_price' => 1, 'quantity' => 1, 'total' => 1]);
        }

        $this->publishLanding();

        $this->get($this->url())
            ->assertOk()
            ->assertSee('اتطلب')
            ->assertSee('طلب جديد من الجيزة')
            ->assertDontSee('سارة')
            ->assertDontSee('01223344556');
    }

    public function test_with_no_orders_the_block_says_nothing_at_all(): void
    {
        $this->publishLanding();

        $this->get($this->url())->assertOk()->assertDontSee('طلب جديد من')->assertDontSee('liveToast', false);
    }

    // ── The builder ─────────────────────────────────────────────────────

    public function test_the_merchant_designs_it_in_the_builder(): void
    {
        $this->actingAs($this->merchant)
            ->get("http://localhost/builder/{$this->key()}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('landing.product_id', $this->product->id)
                ->where('landing.live', false)
                ->where('catalogue', fn ($catalogue) => collect($catalogue)->pluck('type')->contains('landing_hero')));
    }

    public function test_a_merchant_cannot_open_or_write_another_stores_landing_page(): void
    {
        $theirs = Product::factory()->for(Store::factory())->create();

        $this->actingAs($this->merchant)->get("http://localhost/builder/{$this->key($theirs)}")->assertNotFound();
        $this->actingAs($this->merchant)->put("http://localhost/builder/{$this->key($theirs)}", ['sections' => []])->assertNotFound();
        $this->actingAs($this->merchant)->delete("http://localhost/products/{$theirs->id}/landing")->assertNotFound();
    }

    // ── AI ──────────────────────────────────────────────────────────────

    private function claudeWrites(array $plan): void
    {
        config(['services.anthropic.key' => 'sk-ant-test-key', 'ai.daily_cost_cap_usd' => 1.00]);

        Http::fake(['api.anthropic.com/*' => fn () => Http::response([
            'id' => 'msg_landing', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-sonnet-5-5',
            'content' => [['type' => 'text', 'text' => json_encode($plan, JSON_UNESCAPED_UNICODE)]],
            'stop_reason' => 'end_turn', 'stop_sequence' => null,
            'usage' => ['input_tokens' => 2000, 'output_tokens' => 1500],
        ])]);
    }

    private function plan(): array
    {
        return [
            'hero' => ['badge' => '', 'headline' => 'بشرة ناعمة من غير وجع', 'subheadline' => 'كريستالة بتشيل الشعر بلمسة.', 'points' => ['من غير شحن', 'آمنة على البشرة'], 'button' => 'اطلب دلوقتي'],
            'trust_items' => [['icon' => 'wallet', 'title' => 'الدفع عند الاستلام', 'body' => 'ادفع لما توصلك']],
            'benefits_title' => 'ليه هتحبيها؟',
            'benefits_subtitle' => '',
            'benefits' => [['icon' => 'bolt', 'title' => 'سريعة', 'body' => 'دقايق وتخلصي.'], ['icon' => 'leaf', 'title' => 'من غير وجع', 'body' => 'مش زي الشمع.']],
            'comparison' => ['include' => true, 'title' => 'الفرق واضح', 'them_1' => 'الشمع', 'them_2' => 'الموس', 'rows' => [['feature' => 'من غير وجع', 'us' => true, 'them_1' => false, 'them_2' => true]]],
            'guarantee' => ['include' => false, 'badge' => '', 'title' => '', 'body' => ''],
            'faq' => [['question' => 'أدفع إزاي؟', 'answer' => 'كاش لما الطلب يوصلك.']],
            'heads_up' => 'ماذكرتش ضمان، فسبنا الجزء ده برّه.',
        ];
    }

    public function test_ai_writes_the_page_into_the_draft_and_charges_once(): void
    {
        config(['billing.ai_landing_page_price' => 50]);
        app(WalletService::class)->credit($this->store, 200);
        $this->claudeWrites($this->plan());

        $id = $this->actingAs($this->merchant)
            ->postJson('/ai/landing-pages', ['product_id' => $this->product->id, 'notes' => 'مناسبة للبشرة الحساسة'])
            ->assertStatus(202)
            ->json('id');

        $this->assertSame(AiGeneration::STATUS_DONE, AiGeneration::find($id)->status);

        $this->actingAs($this->merchant)->postJson("/ai/landing-pages/{$id}/apply")->assertOk();
        $this->actingAs($this->merchant)->postJson("/ai/landing-pages/{$id}/apply")->assertOk();

        $draft = app(PageBuilder::class)->draft($this->store, $this->key());
        $types = array_column($draft, 'type');

        $this->assertSame('بشرة ناعمة من غير وجع', $draft[0]['settings']['headline']);
        $this->assertContains('comparison', $types);
        $this->assertContains('product_main', $types);
        $this->assertNotContains('guarantee', $types, 'no guarantee is invented');

        // Charged once, however often it is applied — and the live page is untouched.
        $this->assertSame(1, StoreWalletTransaction::where('type', StoreWalletTransaction::TYPE_AI_FEE)->count());
        $this->assertEquals(150, (float) $this->store->fresh()->balance);
        $this->assertFalse(app(PageBuilder::class)->hasLanding($this->store, $this->product->id));
    }

    public function test_it_refuses_before_calling_claude_when_the_wallet_cannot_pay(): void
    {
        config(['billing.ai_landing_page_price' => 50, 'billing.overdraft_limit' => 25]);
        $this->claudeWrites($this->plan());

        $this->actingAs($this->merchant)
            ->postJson('/ai/landing-pages', ['product_id' => $this->product->id])
            ->assertStatus(402);

        Http::assertNothingSent();
    }

    public function test_it_only_writes_pages_for_the_merchants_own_products(): void
    {
        $this->claudeWrites($this->plan());
        $theirs = Product::factory()->for(Store::factory())->create();

        $this->actingAs($this->merchant)->postJson('/ai/landing-pages', ['product_id' => $theirs->id])->assertNotFound();

        Http::assertNothingSent();
    }
}

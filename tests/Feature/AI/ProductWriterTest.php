<?php

namespace Tests\Feature\AI;

use App\Jobs\RunAiGeneration;
use App\Models\AiCall;
use App\Models\AiGeneration;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\AI\AiFeature;
use App\Services\AI\AiUsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The product form's "write it for me" button, end to end: photos in, a draft
 * out, nothing saved.
 *
 * Claude is faked at the HTTP layer — the gateway hands the SDK Laravel's own
 * HTTP stack, so Http::fake() sees the exact request the SDK built, and
 * preventStrayRequests() makes reaching the real API a test failure. The
 * queue is sync in tests, so a POST comes back with the finished draft.
 */
class ProductWriterTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Storage::fake('local');

        config([
            'services.anthropic.key' => 'sk-ant-test-key',
            'ai.daily_cost_cap_usd' => 1.00,
            'ai.features.product_writer.daily_limit' => 20,
        ]);

        $this->merchant = User::factory()->create();
        $this->store = Store::factory()->for($this->merchant)->create();
    }

    /** @param array<string,mixed> $overrides */
    private function draft(array $overrides = []): array
    {
        return array_merge([
            'name' => 'جاكيت جلد رجالي',
            'description_html' => '<p>جاكيت شيك ودافي.</p><h3>ليه هتحبه؟</h3><ul><li>سوستة متينة</li><li>جيبين جانب</li></ul>',
            'seo_title' => 'جاكيت جلد رجالي شتوي',
            'seo_description' => 'جاكيت جلد رجالي دافي وشيك، متاح بأكتر من لون ومقاس.',
            'options' => [
                ['name' => 'اللون', 'values' => ['أسود', 'بني', 'كحلي']],
                ['name' => 'المقاس', 'values' => ['M', 'L', 'XL', 'XXL']],
            ],
            'category_id' => null,
            'heads_up' => 'المقاسات مقترحة، راجعها.',
        ], $overrides);
    }

    /**
     * A Messages API response, as the API sends it.
     *
     * @param  array<string,mixed>  $draft
     * @param  array<string,int>  $usage
     */
    private function claudeReplies(array $draft, string $stopReason = 'end_turn', array $usage = []): void
    {
        $message = $this->message($draft, $stopReason, $usage);

        // A fresh response per request: a shared one's body stream is left at
        // its end by the first read, and the second caller gets nothing.
        Http::fake(['api.anthropic.com/*' => fn () => Http::response($message)]);
    }

    /**
     * @param  array<string,mixed>  $draft
     * @param  array<string,int>  $usage
     * @return array<string,mixed>
     */
    private function message(array $draft, string $stopReason = 'end_turn', array $usage = []): array
    {
        return [
            'id' => 'msg_test_123',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-sonnet-5-5',
            'content' => [['type' => 'text', 'text' => json_encode($draft, JSON_UNESCAPED_UNICODE)]],
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'usage' => $usage + [
                'input_tokens' => 6000,
                'output_tokens' => 1200,
                'cache_creation_input_tokens' => 0,
                'cache_read_input_tokens' => 0,
            ],
        ];
    }

    private function ask(array $payload = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->merchant)->post('/ai/product-drafts', $payload + [
            'notes' => 'جاكيت جلد رجالي، ٣ ألوان، خامة كويسة',
            'images' => [UploadedFile::fake()->image('jacket.jpg', 2400, 1800)],
        ], ['Accept' => 'application/json']);
    }

    public function test_photos_and_notes_come_back_as_a_draft_and_nothing_is_saved(): void
    {
        $this->claudeReplies($this->draft());

        $response = $this->ask()->assertStatus(202);

        $response->assertJsonPath('status', 'done')
            ->assertJsonPath('draft.name', 'جاكيت جلد رجالي')
            ->assertJsonPath('draft.seo_title', 'جاكيت جلد رجالي شتوي')
            ->assertJsonPath('draft.options.0.name', 'اللون')
            ->assertJsonPath('draft.options.1.values', ['M', 'L', 'XL', 'XXL'])
            ->assertJsonPath('draft.heads_up', 'المقاسات مقترحة، راجعها.');

        $this->assertStringContainsString('<li>سوستة متينة</li>', $response->json('draft.description'));

        // The polling endpoint answers the same.
        $this->actingAs($this->merchant)
            ->getJson('/ai/product-drafts/' . $response->json('id'))
            ->assertOk()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('draft.name', 'جاكيت جلد رجالي');

        // A draft, never a product.
        $this->assertSame(0, Product::count());
    }

    public function test_the_request_to_claude_is_the_one_we_mean_to_send(): void
    {
        $this->claudeReplies($this->draft());

        $this->ask()->assertStatus(202);

        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $request) {
            $body = $request->data();
            $content = $body['messages'][0]['content'];
            $image = base64_decode($content[0]['source']['data']);
            [$width] = getimagesizefromstring($image);

            return str_starts_with($request->url(), 'https://api.anthropic.com/v1/messages')
                && $request->hasHeader('Authorization', 'Bearer sk-ant-test-key')
                && str_contains($request->header('anthropic-beta')[0] ?? '', 'server-side-fallback-2026-07-01')
                && $body['model'] === 'claude-sonnet-5-5'
                && $body['fallbacks'] === 'default'
                && $body['output_config']['format']['type'] === 'json_schema'
                && $body['output_config']['effort'] === 'low'
                && $body['system'][0]['cache_control']['type'] === 'ephemeral'
                // The photo, shrunk to what the model looks at.
                && $content[0]['type'] === 'image'
                && $content[0]['source']['media_type'] === 'image/jpeg'
                && $width === 1024
                // The merchant's words reach the model.
                && str_contains($content[1]['text'], 'جاكيت جلد رجالي، ٣ ألوان');
        });
    }

    public function test_every_call_is_metered_with_its_tokens_and_cost(): void
    {
        $this->claudeReplies($this->draft(), usage: ['cache_read_input_tokens' => 1500]);

        $id = $this->ask()->json('id');

        $call = AiCall::sole();

        $this->assertSame($this->store->id, $call->store_id);
        $this->assertSame('product_writer', $call->feature);
        $this->assertSame('claude-sonnet-5-5', $call->model);
        $this->assertSame(AiCall::STATUS_OK, $call->status);
        $this->assertSame(6000, $call->input_tokens);
        $this->assertSame(1200, $call->output_tokens);
        $this->assertSame(1500, $call->cache_read_tokens);
        $this->assertSame('msg_test_123', $call->request_id);
        $this->assertSame(AiGeneration::class, $call->subject_type);
        $this->assertSame($id, $call->subject_id);
        // 6000 × $2 + 1200 × $10 + 1500 × $0.20, per million.
        $this->assertSame('0.024300', $call->cost_usd);
        $this->assertNull($call->store_usage_event_id);
    }

    public function test_the_uploaded_photos_are_deleted_once_the_draft_is_written(): void
    {
        $this->claudeReplies($this->draft());

        $id = $this->ask()->json('id');

        $this->assertSame([], Storage::disk('local')->allFiles("ai-inputs/{$id}"));
    }

    public function test_notes_alone_are_enough(): void
    {
        $this->claudeReplies($this->draft());

        $this->actingAs($this->merchant)
            ->postJson('/ai/product-drafts', ['notes' => 'كوباية قهوة سيراميك'])
            ->assertStatus(202)
            ->assertJsonPath('status', 'done');
    }

    public function test_nothing_to_go_on_is_refused_before_anything_is_spent(): void
    {
        Http::fake();

        $this->actingAs($this->merchant)
            ->postJson('/ai/product-drafts', ['notes' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('images');

        Http::assertNothingSent();
        $this->assertSame(0, AiGeneration::count());
    }

    public function test_the_description_is_sanitised_before_the_form_ever_renders_it(): void
    {
        $this->claudeReplies($this->draft([
            'description_html' => '<p onclick="steal()">حلو</p><script>alert(1)</script><img src=x onerror=alert(1)>',
        ]));

        $description = $this->ask()->json('draft.description');

        $this->assertStringNotContainsString('<script', $description);
        $this->assertStringNotContainsString('onclick', $description);
        $this->assertStringNotContainsString('onerror', $description);
        $this->assertStringContainsString('حلو', $description);
    }

    public function test_a_category_is_suggested_only_from_the_merchants_own_store(): void
    {
        $mine = Category::create(['store_id' => $this->store->id, 'name' => 'جواكت', 'slug' => 'jackets']);
        $theirs = Category::create(['store_id' => Store::factory()->create()->id, 'name' => 'جواكت', 'slug' => 'jackets']);

        Http::fakeSequence('api.anthropic.com/*')
            ->push($this->message($this->draft(['category_id' => $mine->id])))
            ->push($this->message($this->draft(['category_id' => $theirs->id])));

        $this->ask()->assertJsonPath('draft.category_id', $mine->id);
        $this->ask()->assertJsonPath('draft.category_id', null);
    }

    public function test_the_store_categories_are_shown_to_the_model(): void
    {
        Category::create(['store_id' => $this->store->id, 'name' => 'جواكت رجالي', 'slug' => 'jackets']);
        Category::create(['store_id' => Store::factory()->create()->id, 'name' => 'فساتين سهرة', 'slug' => 'dresses']);

        $this->claudeReplies($this->draft());
        $this->ask();

        Http::assertSent(function (HttpRequest $request) {
            $text = collect($request->data()['messages'][0]['content'])->firstWhere('type', 'text')['text'];

            return str_contains($text, 'جواكت رجالي') && ! str_contains($text, 'فساتين سهرة');
        });
    }

    public function test_suggested_options_are_held_to_what_the_product_form_can_save(): void
    {
        $many = array_map(fn ($i) => "قيمة {$i}", range(1, 15));

        $this->claudeReplies($this->draft(['options' => [
            ['name' => 'اللون', 'values' => $many],
            ['name' => 'اللون', 'values' => ['أحمر']],
            ['name' => 'المقاس', 'values' => $many],
            ['name' => 'الخامة', 'values' => $many],
            ['name' => 'الموديل', 'values' => ['أ']],
            ['name' => '', 'values' => ['x']],
        ]]));

        $options = $this->ask()->json('draft.options');

        // Duplicate and empty names dropped; 10 values each; and a third
        // option dropped, because 10 × 10 × 10 combinations is more variants
        // than a product may have.
        $this->assertSame(['اللون', 'المقاس'], array_column($options, 'name'));
        $this->assertCount(10, $options[0]['values']);
    }

    public function test_a_merchant_cannot_read_another_stores_draft(): void
    {
        $this->claudeReplies($this->draft());
        $id = $this->ask()->json('id');

        $stranger = User::factory()->create();
        Store::factory()->for($stranger)->create();

        $this->actingAs($stranger)
            ->getJson("/ai/product-drafts/{$id}")
            ->assertNotFound();
    }

    public function test_a_guest_cannot_ask_for_a_draft(): void
    {
        Http::fake();

        $this->postJson('/ai/product-drafts', ['notes' => 'تيشيرت'])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_the_daily_limit_stops_the_call_before_it_is_made(): void
    {
        config(['ai.features.product_writer.daily_limit' => 1]);
        $this->claudeReplies($this->draft());

        $this->ask()->assertStatus(202);

        $this->ask()
            ->assertStatus(429)
            ->assertJsonPath('message', 'خلصت المحاولات بتاعة النهارده. تقدر تجرّب تاني بكرة.');

        Http::assertSentCount(1);
    }

    public function test_the_daily_limit_is_per_store(): void
    {
        config(['ai.features.product_writer.daily_limit' => 1]);
        $this->claudeReplies($this->draft());

        $this->ask()->assertStatus(202);

        $other = User::factory()->create();
        Store::factory()->for($other)->create();

        $this->actingAs($other)
            ->postJson('/ai/product-drafts', ['notes' => 'شنطة'])
            ->assertStatus(202);
    }

    public function test_yesterdays_calls_do_not_count_against_today(): void
    {
        config(['ai.features.product_writer.daily_limit' => 1]);
        AiCall::create([
            'store_id' => $this->store->id, 'feature' => 'product_writer', 'model' => 'claude-sonnet-5-5',
            'status' => AiCall::STATUS_OK, 'cost_usd' => 5,
        ])->forceFill(['created_at' => now()->subDay()])->save();

        $this->claudeReplies($this->draft());

        $this->ask()->assertStatus(202)->assertJsonPath('status', 'done');
    }

    public function test_the_spend_cap_stops_a_store_that_has_spent_its_share(): void
    {
        config(['ai.daily_cost_cap_usd' => 0.05]);
        AiCall::create([
            'store_id' => $this->store->id, 'feature' => 'product_writer', 'model' => 'claude-sonnet-5-5',
            'status' => AiCall::STATUS_OK, 'cost_usd' => 0.05,
        ]);
        Http::fake();

        $this->ask()->assertStatus(429);

        Http::assertNothingSent();
    }

    public function test_a_second_request_waits_for_the_first(): void
    {
        AiGeneration::create([
            'store_id' => $this->store->id, 'feature' => 'product_writer', 'status' => AiGeneration::STATUS_RUNNING,
        ]);
        Http::fake();

        $this->ask()->assertStatus(429);

        Http::assertNothingSent();
    }

    public function test_without_an_api_key_the_merchant_is_told_it_is_unavailable(): void
    {
        config(['services.anthropic.key' => null]);
        Http::fake();

        $this->ask()
            ->assertStatus(202)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('error', 'الخدمة دي مش متاحة دلوقتي. جرّب تاني بعدين.');

        Http::assertNothingSent();
    }

    public function test_a_refusal_fails_the_draft_and_still_counts(): void
    {
        $this->claudeReplies([], 'refusal');

        $this->ask()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('error', 'مقدرناش نعمل الطلب ده. جرّب تاني بصور أو كلام مختلف.');

        $this->assertSame(AiCall::STATUS_REFUSED, AiCall::sole()->status);
    }

    public function test_an_answer_cut_off_mid_way_is_not_passed_off_as_a_draft(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'id' => 'msg_cut', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-sonnet-5-5',
            'content' => [['type' => 'text', 'text' => '{"name": "جاكيت", "description_html": "<p>جاكيت']],
            'stop_reason' => 'max_tokens', 'stop_sequence' => null,
            'usage' => ['input_tokens' => 6000, 'output_tokens' => 4000],
        ])]);

        $this->ask()->assertJsonPath('status', 'failed');

        $this->assertSame(AiCall::STATUS_INCOMPLETE, AiCall::sole()->status);
    }

    public function test_a_bad_key_fails_the_draft_without_leaking_the_api_error(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key'],
        ], 401)]);

        $response = $this->ask()->assertJsonPath('status', 'failed');

        $this->assertStringNotContainsString('x-api-key', $response->json('error'));
        $call = AiCall::sole();
        $this->assertSame(AiCall::STATUS_FAILED, $call->status);
        $this->assertStringContainsString('authentication_error', $call->error);
    }

    public function test_a_failed_call_does_not_use_up_the_merchants_tries(): void
    {
        config(['ai.features.product_writer.daily_limit' => 1]);
        Http::fakeSequence('api.anthropic.com/*')
            ->push(['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'bad']], 401)
            ->push($this->message($this->draft()));

        $this->ask()->assertJsonPath('status', 'failed');

        $this->ask()->assertStatus(202)->assertJsonPath('status', 'done');
    }

    public function test_a_busy_api_is_retried_once_before_the_merchant_sees_an_error(): void
    {
        Http::fake(['api.anthropic.com/*' => fn () => Http::response([
            'type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded'],
        ], 529)]);

        $generation = AiGeneration::create([
            'store_id' => $this->store->id, 'feature' => 'product_writer',
            'status' => AiGeneration::STATUS_PENDING, 'input' => ['notes' => 'شنطة جلد'],
        ]);

        // First attempt: put back on the queue, and the form keeps waiting.
        $job = (new RunAiGeneration($generation->id))->withFakeQueueInteractions();
        $job->handle();

        $job->assertReleased(15);
        $this->assertSame(AiGeneration::STATUS_RUNNING, $generation->fresh()->status);

        // Last attempt: now the merchant is told.
        $job = (new RunAiGeneration($generation->id))->withFakeQueueInteractions();
        $job->job->attempts = 2;
        $job->handle();

        $job->assertNotReleased();
        $this->assertSame(AiGeneration::STATUS_FAILED, $generation->fresh()->status);
        $this->assertSame('الخدمة عليها ضغط دلوقتي. جرّب تاني كمان شوية.', $generation->fresh()->error);

        // Two attempts, nothing billed, nothing counted against the merchant.
        $this->assertSame(2, AiCall::where('status', AiCall::STATUS_FAILED)->count());
        $this->assertSame(20, app(AiUsageMeter::class)->remainingToday($this->store, AiFeature::ProductWriter));
    }

    public function test_a_draft_nobody_answered_expires_instead_of_spinning_forever(): void
    {
        $generation = AiGeneration::create([
            'store_id' => $this->store->id, 'feature' => 'product_writer', 'status' => AiGeneration::STATUS_PENDING,
        ]);
        $generation->forceFill(['created_at' => now()->subMinutes(10)])->save();

        $this->actingAs($this->merchant)
            ->getJson("/ai/product-drafts/{$generation->id}")
            ->assertJsonPath('status', 'failed');
    }
}

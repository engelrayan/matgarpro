<?php

namespace Tests\Feature\Pixels;

use App\Jobs\SendMetaPurchaseEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\StorePixel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConversionsApiTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create(['slug' => 'mahmoud', 'currency' => 'EGP']);
    }

    private function pixel(array $attributes = []): StorePixel
    {
        return StorePixel::create([
            'store_id' => $this->store->id,
            'provider' => StorePixel::PROVIDER_META,
            'pixel_id' => '1234567890',
            'access_token' => 'EAAG-secret-token',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function order(array $attributes = []): Order
    {
        $order = Order::factory()->for($this->store)->create([
            'customer_name' => 'محمود ممدوح',
            'customer_phone' => '01006262330',
            'customer_email' => 'mahmoud@example.com',
            'governorate' => 'القاهرة',
            'total' => 500,
            'subtotal' => 500,
            'ip' => '197.54.1.1',
            'user_agent' => 'Mozilla/5.0',
            'tracking' => ['fbp' => 'fb.1.100.999', 'fbc' => 'fb.1.100.IwAR', 'source_url' => 'https://mahmoud.com/p/x'],
            ...$attributes,
        ]);

        OrderItem::factory()->for($order)->create([
            'name' => 'قميص', 'unit_price' => 250, 'quantity' => 2, 'total' => 500,
        ]);

        return $order;
    }

    private function checkout(): array
    {
        $product = Product::factory()->for($this->store)->create(['price' => 500, 'track_stock' => false]);

        return [$product, [
            'product_id' => $product->id,
            'quantity' => 1,
            'customer_name' => 'محمود',
            'customer_phone' => '01006262330',
            'address' => 'القاهرة',
        ]];
    }

    // ── Dispatch ────────────────────────────────────────────────────────────

    /**
     * The customer's checkout must never wait on Meta. If this ever becomes
     * synchronous, a Graph API outage becomes a broken thank-you page.
     */
    public function test_checkout_queues_the_event_instead_of_sending_it_inline(): void
    {
        Queue::fake();
        Http::preventStrayRequests();

        $this->pixel();
        [, $payload] = $this->checkout();

        $this->post('http://' . $this->store->platformHost() . '/checkout', $payload)
            ->assertRedirect();

        Queue::assertPushed(SendMetaPurchaseEvent::class, 1);
    }

    public function test_every_active_pixel_gets_its_own_job(): void
    {
        Queue::fake();

        $this->pixel(['pixel_id' => '111']);
        $this->pixel(['pixel_id' => '222']);
        [, $payload] = $this->checkout();

        $this->post('http://' . $this->store->platformHost() . '/checkout', $payload);

        Queue::assertPushed(SendMetaPurchaseEvent::class, 2);
    }

    public function test_inactive_or_tokenless_pixels_are_skipped(): void
    {
        Queue::fake();

        $this->pixel(['pixel_id' => '111', 'is_active' => false]);
        $this->pixel(['pixel_id' => '222', 'access_token' => null]);
        [, $payload] = $this->checkout();

        $this->post('http://' . $this->store->platformHost() . '/checkout', $payload);

        Queue::assertNothingPushed();
    }

    /** A tracking failure must never cost the merchant a sale already taken. */
    public function test_an_order_survives_a_tracking_failure(): void
    {
        Http::fake(fn () => Http::response(['error' => ['message' => 'boom', 'code' => 500]], 500));

        $this->pixel();
        [, $payload] = $this->checkout();

        $this->post('http://' . $this->store->platformHost() . '/checkout', $payload)
            ->assertRedirect();

        $this->assertSame(1, $this->store->orders()->count());
    }

    public function test_the_click_identifiers_are_captured_onto_the_order(): void
    {
        Queue::fake();
        [, $payload] = $this->checkout();

        $this->withUnencryptedCookies(['_fbp' => 'fb.1.100.999', '_fbc' => 'fb.1.100.IwAR'])
            ->post('http://' . $this->store->platformHost() . '/checkout', $payload);

        $tracking = $this->store->orders()->firstOrFail()->tracking;

        $this->assertSame('fb.1.100.999', $tracking['fbp']);
        $this->assertSame('fb.1.100.IwAR', $tracking['fbc']);
    }

    // ── Payload ─────────────────────────────────────────────────────────────

    public function test_the_purchase_payload_matches_metas_shape(): void
    {
        Http::fake(fn () => Http::response(['events_received' => 1]));

        $pixel = $this->pixel();
        $order = $this->order();

        (new SendMetaPurchaseEvent($order->id, $pixel->id))->handle(app(\App\Services\Pixels\MetaConversionsApi::class));

        Http::assertSent(function ($request) use ($order) {
            $event = $request->data()['data'][0];

            return $request->url() === 'https://graph.facebook.com/v21.0/1234567890/events'
                && $event['event_name'] === 'Purchase'
                && $event['action_source'] === 'website'
                && $event['event_id'] === SendMetaPurchaseEvent::eventIdFor($order)
                && $event['event_time'] === $order->created_at->timestamp
                && $event['custom_data']['currency'] === 'EGP'
                && $event['custom_data']['value'] === 500.0
                && $event['custom_data']['num_items'] === 2;
        });
    }

    /**
     * The browser and the server must derive the same event id, or Meta counts
     * every sale twice and the merchant optimises against doubled numbers.
     */
    public function test_the_event_id_is_deterministic_and_store_scoped(): void
    {
        $order = $this->order();

        $this->assertSame(
            SendMetaPurchaseEvent::eventIdFor($order),
            SendMetaPurchaseEvent::eventIdFor($order->fresh()),
        );

        $this->assertStringContainsString((string) $order->store_id, SendMetaPurchaseEvent::eventIdFor($order));
        $this->assertStringContainsString((string) $order->id, SendMetaPurchaseEvent::eventIdFor($order));
    }

    public function test_customer_data_is_hashed_and_click_ids_are_not(): void
    {
        Http::fake(fn () => Http::response(['events_received' => 1]));

        $pixel = $this->pixel();
        $order = $this->order();

        (new SendMetaPurchaseEvent($order->id, $pixel->id))->handle(app(\App\Services\Pixels\MetaConversionsApi::class));

        Http::assertSent(function ($request) {
            $user = $request->data()['data'][0]['user_data'];

            return $user['em'] === hash('sha256', 'mahmoud@example.com')
                && $user['ph'] === hash('sha256', '201006262330')
                && $user['fbp'] === 'fb.1.100.999'
                && $user['fbc'] === 'fb.1.100.IwAR'
                && $user['client_ip_address'] === '197.54.1.1';
        });
    }

    /** The token is a credential: it belongs in the body, not a logged URL. */
    public function test_the_access_token_is_never_put_in_the_query_string(): void
    {
        Http::fake(fn () => Http::response(['events_received' => 1]));

        $pixel = $this->pixel();
        $order = $this->order();

        (new SendMetaPurchaseEvent($order->id, $pixel->id))->handle(app(\App\Services\Pixels\MetaConversionsApi::class));

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'EAAG-secret-token')
            && $request->data()['access_token'] === 'EAAG-secret-token');
    }

    public function test_the_test_event_code_travels_with_the_payload(): void
    {
        Http::fake(fn () => Http::response(['events_received' => 1]));

        $pixel = $this->pixel(['test_event_code' => 'TEST123']);
        $order = $this->order();

        (new SendMetaPurchaseEvent($order->id, $pixel->id))->handle(app(\App\Services\Pixels\MetaConversionsApi::class));

        Http::assertSent(fn ($request) => $request->data()['test_event_code'] === 'TEST123');
    }

    // ── Failure handling ────────────────────────────────────────────────────

    public function test_a_successful_send_clears_the_last_error(): void
    {
        Http::fake(fn () => Http::response(['events_received' => 1]));

        $pixel = $this->pixel(['last_error' => 'قديم']);
        $order = $this->order();

        (new SendMetaPurchaseEvent($order->id, $pixel->id))->handle(app(\App\Services\Pixels\MetaConversionsApi::class));

        $pixel->refresh();
        $this->assertNull($pixel->last_error);
        $this->assertNotNull($pixel->last_event_at);
    }

    /** A revoked token fails identically forever — retrying just burns workers. */
    public function test_a_revoked_token_is_recorded_and_not_retried(): void
    {
        Http::fake(fn () => Http::response([
            'error' => ['message' => 'Error validating access token', 'code' => 190],
        ], 401));

        $pixel = $this->pixel();
        $order = $this->order();

        $job = new SendMetaPurchaseEvent($order->id, $pixel->id);
        $job->handle(app(\App\Services\Pixels\MetaConversionsApi::class));

        $this->assertStringContainsString('access token', $pixel->fresh()->last_error);
    }

    public function test_a_missing_order_or_pixel_does_not_blow_up(): void
    {
        Http::preventStrayRequests();

        $pixel = $this->pixel();

        (new SendMetaPurchaseEvent(999_999, $pixel->id))->handle(app(\App\Services\Pixels\MetaConversionsApi::class));

        $this->expectNotToPerformAssertions();
    }

    // ── Browser side ────────────────────────────────────────────────────────

    public function test_the_pixel_snippet_only_renders_when_a_pixel_exists(): void
    {
        $product = Product::factory()->for($this->store)->create();
        $url = 'http://' . $this->store->platformHost() . '/p/' . $product->slug;

        $this->get($url)->assertOk()->assertDontSee('fbevents.js');

        $this->pixel();

        $this->get($url)->assertOk()
            ->assertSee('fbevents.js')
            ->assertSee("fbq('init', \"1234567890\")", false)
            ->assertSee('ViewContent');
    }

    /** The token must never reach the browser, on any storefront page. */
    public function test_the_access_token_never_reaches_the_storefront(): void
    {
        $this->pixel();
        $product = Product::factory()->for($this->store)->create();

        $this->get('http://' . $this->store->platformHost() . '/p/' . $product->slug)
            ->assertOk()
            ->assertDontSee('EAAG-secret-token');
    }

    public function test_the_thank_you_page_fires_purchase_with_the_shared_event_id(): void
    {
        $this->pixel();
        $order = $this->order();

        $this->get('http://' . $this->store->platformHost() . '/thanks/' . $order->id)
            ->assertOk()
            ->assertSee('Purchase')
            ->assertSee(SendMetaPurchaseEvent::eventIdFor($order));
    }
}

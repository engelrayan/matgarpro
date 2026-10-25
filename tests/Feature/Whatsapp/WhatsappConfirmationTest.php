<?php

namespace Tests\Feature\Whatsapp;

use App\Jobs\SendWhatsappOrderConfirmation;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreWhatsappIntegration;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The confirmation round trip — sent, billed, answered — on the store's own
 * number. The platform number's version is PlatformWhatsappTest; WAHA's own
 * plumbing (linking, polls, hidden ids) is WahaTest.
 */
class WhatsappConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'waha-webhook-secret';

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.whatsapp.waha' => [
            'url' => 'http://waha.test',
            'api_key' => 'waha-key',
            'webhook_secret' => self::SECRET,
            'callback_url' => 'https://app.matgarpro.test',
            'engine' => 'GOWS',
        ]]);

        $this->user = User::factory()->create();
        $this->store = Store::factory()->for($this->user)->create();
    }

    private function link(array $attributes = []): StoreWhatsappIntegration
    {
        return $this->store->whatsappIntegration()->create([
            'driver' => StoreWhatsappIntegration::DRIVER_WAHA,
            'credentials' => ['status' => 'WORKING'],
            'is_active' => true,
            'auto_send' => true,
            'webhook_token' => StoreWhatsappIntegration::newToken(),
            'verify_token' => StoreWhatsappIntegration::newToken(),
            'connected_at' => now(),
            ...$attributes,
        ]);
    }

    private function order(array $attributes = []): Order
    {
        $order = Order::factory()->for($this->store)->create([
            'status' => Order::STATUS_PENDING,
            'customer_name' => 'سارة عبد الله',
            'customer_phone' => '01223344556',
            'total' => 450,
            ...$attributes,
        ]);

        $order->items()->create([
            'name' => 'قميص قطن', 'variant_label' => 'أبيض · L',
            'unit_price' => 225, 'quantity' => 2, 'total' => 450,
        ]);

        return $order;
    }

    /** A typed reply, as WAHA posts it — signed. */
    private function reply(StoreWhatsappIntegration $link, string $body, string $from = '201223344556', bool $fromMe = false): \Illuminate\Testing\TestResponse
    {
        $json = json_encode(['event' => 'message', 'session' => 'store-' . $this->store->id, 'payload' => [
            'id' => 'false_' . $from . '@c.us_' . md5($body), 'from' => $from . '@c.us', 'fromMe' => $fromMe, 'body' => $body,
        ]], JSON_UNESCAPED_UNICODE);

        return $this->call('POST', "/api/integrations/whatsapp/webhook/{$link->webhook_token}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_HMAC' => hash_hmac('sha512', $json, self::SECRET),
        ], $json);
    }

    private function send(Order $order): void
    {
        (new SendWhatsappOrderConfirmation($order->id))->handle(app(WhatsappSender::class));
    }

    // ── Settings ────────────────────────────────────────────────────────────

    public function test_the_page_renders(): void
    {
        $this->actingAs($this->user)
            ->get('http://localhost/settings/whatsapp')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Whatsapp')
                ->where('integration.connected', false));
    }

    /** Wapilot, Whats360 and a merchant's own Cloud API keys are gone. */
    public function test_there_is_no_gateway_form_any_more(): void
    {
        $this->actingAs($this->user)
            ->put('http://localhost/settings/whatsapp', ['driver' => 'wapilot', 'token' => 'x', 'instance' => 'y'])
            ->assertMethodNotAllowed();

        $this->assertNull($this->store->fresh()->whatsappIntegration);
    }

    // ── Sending ─────────────────────────────────────────────────────────────

    public function test_it_messages_the_customer_with_the_order_in_it(): void
    {
        $this->link();
        $order = $this->order();

        Http::fake(['waha.test/api/sendPoll' => Http::response(['id' => 'true_201223344556@c.us_OUT1'])]);

        $this->send($order);

        Http::assertSent(fn ($request) => $request['chatId'] === '201223344556@c.us'
            && str_contains($request['poll']['name'], 'سارة عبد الله')
            && str_contains($request['poll']['name'], '450.00'));

        $order->refresh();

        $this->assertSame('sent', $order->whatsapp_state);
        $this->assertNotNull($order->whatsapp_sent_at);
        $this->assertDatabaseHas('whatsapp_messages', [
            'order_id' => $order->id,
            'direction' => 'out',
            'status' => 'sent',
            'provider_message_id' => 'true_201223344556@c.us_OUT1',
        ]);
    }

    public function test_a_refused_message_is_recorded_on_the_order(): void
    {
        $this->link();
        $order = $this->order();

        Http::fake(['waha.test/api/sendPoll' => Http::response(['message' => 'Session closed'], 400)]);

        $this->send($order);

        $order->refresh();

        $this->assertSame('failed', $order->whatsapp_state);
        $this->assertNotNull($order->whatsapp_error);
        // The customer was never told anything, so the order stays where it was.
        $this->assertSame(Order::STATUS_PENDING, $order->status);
    }

    /** The confirmation is billed when it goes out — and only then. */
    public function test_a_sent_confirmation_is_charged_and_a_failed_one_is_not(): void
    {
        config(['billing.whatsapp_fees.waha' => 0.50]);
        $this->store->update(['price_per_order_override' => 0.75]);
        $this->link();
        $sent = $this->order();
        $failed = $this->order(['customer_phone' => '01001112223']);

        Http::fakeSequence('waha.test/api/sendPoll')
            ->push(['id' => 'true_x@c.us_OUT1'])
            ->push(['message' => 'Session closed'], 400);

        $this->send($sent);
        $this->send($failed);

        $charges = \App\Models\StoreUsageEvent::where('type', \App\Models\StoreUsageEvent::TYPE_WHATSAPP_CONFIRMATION)->get();

        $this->assertCount(1, $charges);
        $this->assertSame($sent->id, (int) $charges->first()->billable_id);
        $this->assertSame('0.50', (string) $charges->first()->amount);
    }

    public function test_a_customer_is_not_messaged_twice_about_the_same_order(): void
    {
        $this->link();
        $order = $this->order(['whatsapp_state' => 'sent', 'whatsapp_sent_at' => now()]);

        Http::fake();

        $this->send($order);

        Http::assertNothingSent();
    }

    // ── Replies ─────────────────────────────────────────────────────────────

    public function test_a_yes_confirms_the_order(): void
    {
        $link = $this->link();
        $order = $this->order(['whatsapp_state' => 'sent', 'whatsapp_sent_at' => now()]);
        Http::fake();

        $this->reply($link, 'تمام يا فندم')->assertOk();

        $order->refresh();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertSame('confirmed', $order->whatsapp_state);
        $this->assertNotNull($order->whatsapp_replied_at);
    }

    public function test_a_no_cancels_it(): void
    {
        $link = $this->link();
        $order = $this->order(['whatsapp_state' => 'sent', 'whatsapp_sent_at' => now()]);
        Http::fake();

        $this->reply($link, 'لا مش عايز')->assertOk();

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
    }

    /**
     * The number arrives E.164 and the order carries whatever the customer
     * typed into the form. Same person either way.
     */
    public function test_it_matches_the_customer_however_they_typed_their_number(): void
    {
        $link = $this->link();
        $order = $this->order(['customer_phone' => '0122 334 4556', 'whatsapp_state' => 'sent']);
        Http::fake();

        $this->reply($link, '1', from: '201223344556')->assertOk();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    public function test_a_reply_it_cannot_read_moves_nothing(): void
    {
        $link = $this->link();
        $order = $this->order(['whatsapp_state' => 'sent']);

        $this->reply($link, 'هيوصل امتى؟')->assertOk();

        $order->refresh();

        // Still waiting: a merchant will read this one themselves.
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame('sent', $order->whatsapp_state);
        $this->assertDatabaseHas('whatsapp_messages', [
            'order_id' => $order->id,
            'direction' => 'in',
            'intent' => WhatsappMessage::INTENT_UNKNOWN,
        ]);
    }

    public function test_a_reply_from_a_stranger_is_only_logged(): void
    {
        $link = $this->link();

        $this->reply($link, '1', from: '201999888777')->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', [
            'store_id' => $this->store->id,
            'order_id' => null,
            'direction' => 'in',
        ]);
    }

    /**
     * Once the merchant has decided by hand, a late reply does not overrule
     * them — they may already have called the customer.
     */
    public function test_it_does_not_reopen_an_order_the_merchant_already_handled(): void
    {
        $link = $this->link();
        $order = $this->order(['status' => Order::STATUS_CONFIRMED, 'whatsapp_state' => 'sent']);

        $this->reply($link, 'لا')->assertOk();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    public function test_our_own_outgoing_messages_are_ignored(): void
    {
        $link = $this->link();
        $order = $this->order(['whatsapp_state' => 'sent']);

        $this->reply($link, 'تمام', fromMe: true)->assertOk();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_an_unknown_webhook_token_is_refused(): void
    {
        $this->postJson('/api/integrations/whatsapp/webhook/' . str_repeat('z', 40), [])
            ->assertStatus(404);
    }
}

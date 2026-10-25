<?php

namespace Tests\Feature\Whatsapp;

use App\Jobs\SendWhatsappOrderConfirmation;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreUsageEvent;
use App\Models\StoreWhatsappIntegration;
use App\Models\User;
use App\Services\Whatsapp\WahaDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The store's own number through our WAHA gateway: linked by QR, confirmed by
 * a poll, priced as its own channel.
 */
class WahaTest extends TestCase
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
        $this->store = Store::factory()->for($this->user)->create(['name' => 'متجر الأناقة']);
    }

    private function linked(string $status = 'WORKING'): StoreWhatsappIntegration
    {
        return $this->store->whatsappIntegration()->create([
            'driver' => StoreWhatsappIntegration::DRIVER_WAHA,
            'credentials' => ['status' => $status],
            'sender_phone' => '201000000001',
            'is_active' => true,
            'auto_send' => true,
            'webhook_token' => StoreWhatsappIntegration::newToken(),
            'verify_token' => StoreWhatsappIntegration::newToken(),
        ]);
    }

    private function order(array $attributes = []): Order
    {
        $order = Order::factory()->for($this->store)->create([
            'status' => Order::STATUS_PENDING, 'customer_name' => 'سارة', 'customer_phone' => '01223344556', ...$attributes,
        ]);
        $order->items()->create(['name' => 'قميص', 'unit_price' => 450, 'quantity' => 1, 'total' => 450]);

        return $order;
    }

    /** POST a WAHA webhook, signed the way WAHA signs it. */
    private function webhook(StoreWhatsappIntegration $link, array $body, ?string $secret = self::SECRET): \Illuminate\Testing\TestResponse
    {
        $json = json_encode(['session' => 'store-' . $this->store->id, ...$body], JSON_UNESCAPED_UNICODE);
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_WEBHOOK_HMAC_ALGORITHM' => 'sha512'];

        if ($secret !== null) {
            $server['HTTP_X_WEBHOOK_HMAC'] = hash_hmac('sha512', $json, $secret);
        }

        return $this->call('POST', "/api/integrations/whatsapp/webhook/{$link->webhook_token}", [], [], [], $server, $json);
    }

    private function vote(string $option, string $from = '201223344556@c.us'): array
    {
        return ['event' => 'poll.vote', 'payload' => [
            'vote' => ['id' => 'v1', 'from' => $from, 'fromMe' => false, 'selectedOptions' => [$option]],
            'poll' => ['id' => 'poll-1'],
        ]];
    }

    // ── Linking ─────────────────────────────────────────────────────────

    public function test_linking_starts_a_session_named_after_the_store_with_a_signed_webhook(): void
    {
        Http::fake([
            'waha.test/api/sessions/store-*' => Http::response([], 404),
            'waha.test/api/sessions' => Http::response(['name' => 'store-' . $this->store->id, 'status' => 'STARTING']),
        ]);

        $this->actingAs($this->user)
            ->post('http://localhost/settings/whatsapp/waha')
            ->assertSessionHas('status', 'whatsapp-waha-started');

        $link = $this->store->fresh()->whatsappIntegration;
        $this->assertSame(StoreWhatsappIntegration::DRIVER_WAHA, $link->driver);
        $this->assertFalse($link->canSend(), 'nothing is sent until the phone has scanned the code');

        Http::assertSent(fn ($request) => $request->url() === 'http://waha.test/api/sessions'
            && $request->hasHeader('X-Api-Key', 'waha-key')
            && $request['name'] === 'store-' . $this->store->id
            && $request['config']['webhooks'][0]['url'] === "https://app.matgarpro.test/api/integrations/whatsapp/webhook/{$link->webhook_token}"
            && $request['config']['webhooks'][0]['hmac']['key'] === self::SECRET
            && in_array('poll.vote', $request['config']['webhooks'][0]['events'], true));
    }

    /**
     * Nobody scanned in time, so WAHA parked the session in FAILED. Pressing
     * «كود جديد» must restart it — "start" on a failed session does nothing.
     */
    public function test_a_session_whose_codes_ran_out_is_restarted_for_fresh_ones(): void
    {
        $this->linked('FAILED');
        Http::fake([
            'waha.test/api/sessions/store-*/restart' => Http::response(['status' => 'STARTING']),
            'waha.test/api/sessions/store-*' => Http::response(['name' => 'store-' . $this->store->id, 'status' => 'FAILED']),
        ]);

        $this->actingAs($this->user)->post('http://localhost/settings/whatsapp/waha')->assertSessionHas('status', 'whatsapp-waha-started');

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/restart'));
    }

    public function test_a_linked_number_is_never_restarted_by_relinking(): void
    {
        $this->linked();
        Http::fake(['waha.test/api/sessions/store-*' => Http::response(['name' => 'store-' . $this->store->id, 'status' => 'WORKING'])]);

        $this->actingAs($this->user)->post('http://localhost/settings/whatsapp/waha');

        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/restart'));
    }

    public function test_the_screen_gets_the_qr_while_waiting_and_the_number_once_linked(): void
    {
        $link = $this->linked('STARTING');

        Http::fake([
            'waha.test/api/sessions/*' => Http::sequence()
                ->push(['status' => 'SCAN_QR_CODE', 'me' => null])
                // GOWS reports the linked device with a suffix after the number.
                ->push(['status' => 'WORKING', 'me' => ['id' => '201009998887:14@c.us', 'pushName' => 'متجر']]),
            'waha.test/api/*/auth/qr' => Http::response(['mimetype' => 'image/png', 'data' => 'QRBASE64']),
        ]);

        $this->actingAs($this->user)->getJson('http://localhost/settings/whatsapp/waha/status')
            ->assertOk()->assertJson(['status' => 'SCAN_QR_CODE', 'qr' => 'QRBASE64']);

        $this->actingAs($this->user)->getJson('http://localhost/settings/whatsapp/waha/status')
            ->assertJson(['status' => 'WORKING', 'phone' => '201009998887', 'qr' => null]);

        $this->assertTrue($link->fresh()->canSend());
        $this->assertSame('201009998887', $link->fresh()->sender_phone);
    }

    public function test_it_is_not_offered_without_a_waha_server(): void
    {
        config(['services.whatsapp.waha.url' => null]);

        $this->actingAs($this->user)->post('http://localhost/settings/whatsapp/waha')->assertNotFound();
    }

    public function test_disconnecting_removes_the_session_from_waha(): void
    {
        $this->linked();
        Http::fake(['waha.test/*' => Http::response([])]);

        $this->actingAs($this->user)->delete('http://localhost/settings/whatsapp')->assertRedirect();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/api/sessions/store-' . $this->store->id));
        $this->assertNull($this->store->fresh()->whatsappIntegration);
    }

    // ── Sending ─────────────────────────────────────────────────────────

    public function test_the_confirmation_is_the_message_then_a_poll_and_it_is_billed_at_the_waha_price(): void
    {
        config(['billing.whatsapp_fees.waha' => 0.25, 'billing.whatsapp_fees.meta' => 0.50]);
        $this->store->update(['price_per_order_override' => 0.75]);
        $this->linked();
        $order = $this->order();

        Http::fake([
            'waha.test/api/sendText' => Http::response(['id' => 'text-1']),
            'waha.test/api/sendPoll' => Http::response(['id' => 'poll-1']),
        ]);

        SendWhatsappOrderConfirmation::dispatchSync($order->id);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/sendText')
            && $request['chatId'] === '201223344556@c.us'
            && str_contains($request['text'], 'متجر الأناقة'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/sendPoll')
            && $request['poll']['options'] === [WahaDriver::CONFIRM_OPTION, WahaDriver::CANCEL_OPTION]);

        $this->assertSame('sent', $order->fresh()->whatsapp_state);
        $this->assertDatabaseHas('whatsapp_messages', ['order_id' => $order->id, 'provider_message_id' => 'poll-1']);
        $this->assertEquals(0.25, StoreUsageEvent::where('type', StoreUsageEvent::TYPE_WHATSAPP_CONFIRMATION)->value('unit_price'));
    }

    public function test_each_channel_has_its_own_price(): void
    {
        config(['billing.whatsapp_fees.waha' => 0.25, 'billing.whatsapp_fees.meta' => 0.60, 'billing.whatsapp_confirmation_fee' => 0.50]);
        $this->store->update(['price_per_order_override' => 0.75]);

        $this->assertSame(0.25, $this->store->confirmationPrice(StoreWhatsappIntegration::DRIVER_WAHA));
        $this->assertSame(0.60, $this->store->confirmationPrice(StoreWhatsappIntegration::DRIVER_PLATFORM));
        $this->assertSame(0.50, $this->store->confirmationPrice(StoreWhatsappIntegration::DRIVER_WAPILOT));
    }

    public function test_a_logged_out_number_is_never_sent_to(): void
    {
        $this->linked('SCAN_QR_CODE');
        $order = $this->order();
        Http::fake();

        SendWhatsappOrderConfirmation::dispatchSync($order->id);

        Http::assertNothingSent();
        $this->assertNull($order->fresh()->whatsapp_state);
    }

    // ── Replies ─────────────────────────────────────────────────────────

    public function test_choosing_confirm_in_the_poll_confirms_the_order_and_thanks_the_customer(): void
    {
        $link = $this->linked();
        $order = $this->order(['whatsapp_state' => 'sent']);
        Http::fake(['waha.test/*' => Http::response(['id' => 'ack-1'])]);

        $this->webhook($link, $this->vote(WahaDriver::CONFIRM_OPTION))->assertOk();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/sendText') && str_contains($request['text'], 'اتأكد'));
    }

    public function test_choosing_cancel_cancels_it(): void
    {
        $link = $this->linked();
        $order = $this->order(['whatsapp_state' => 'sent']);
        Http::fake(['waha.test/*' => Http::response([])]);

        $this->webhook($link, $this->vote(WahaDriver::CANCEL_OPTION))->assertOk();

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_a_typed_reply_still_works(): void
    {
        $link = $this->linked();
        $order = $this->order(['whatsapp_state' => 'sent']);
        Http::fake(['waha.test/*' => Http::response([])]);

        $this->webhook($link, ['event' => 'message', 'payload' => [
            'id' => 'm1', 'from' => '201223344556@c.us', 'fromMe' => false, 'body' => 'تمام يا فندم',
        ]])->assertOk();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    public function test_our_own_messages_groups_and_unsigned_bodies_move_nothing(): void
    {
        $link = $this->linked();
        $order = $this->order(['whatsapp_state' => 'sent']);
        Http::fake();

        $this->webhook($link, ['event' => 'message', 'payload' => ['from' => '201223344556@c.us', 'fromMe' => true, 'body' => 'تمام']]);
        $this->webhook($link, ['event' => 'message', 'payload' => ['from' => '1203630@g.us', 'fromMe' => false, 'body' => 'تمام']]);
        $this->webhook($link, $this->vote(WahaDriver::CONFIRM_OPTION), secret: null)->assertUnauthorized();
        $this->webhook($link, $this->vote(WahaDriver::CONFIRM_OPTION), secret: 'wrong')->assertUnauthorized();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_a_dropped_session_stops_sending_and_tells_the_merchant(): void
    {
        $link = $this->linked();

        $this->webhook($link, ['event' => 'session.status', 'payload' => ['status' => 'SCAN_QR_CODE']])->assertOk();

        $link->refresh();
        $this->assertFalse($link->canSend());
        $this->assertStringContainsString('امسح', (string) $link->last_error);
    }

    /** While the first QR is on screen, "waiting for a scan" is not a fault. */
    public function test_the_first_scan_is_not_reported_as_a_disconnection(): void
    {
        $link = $this->linked('STARTING');

        $this->webhook($link, ['event' => 'session.status', 'payload' => ['status' => 'SCAN_QR_CODE']])->assertOk();

        $this->assertNull($link->fresh()->last_error);
        $this->assertSame('SCAN_QR_CODE', $link->fresh()->credential('status'));
    }
}

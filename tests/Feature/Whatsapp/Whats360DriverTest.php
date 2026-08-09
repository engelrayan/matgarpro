<?php

namespace Tests\Feature\Whatsapp;

use App\Models\Order;
use App\Models\Store;
use App\Models\StoreWhatsappIntegration;
use App\Models\User;
use App\Services\Whatsapp\Whats360Driver;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Whats360's published contract, and the two ways it fails that a status code
 * alone would not catch.
 */
class Whats360DriverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->store = Store::factory()->for($this->user)->create();
    }

    private function link(array $credentials = []): StoreWhatsappIntegration
    {
        return $this->store->whatsappIntegration()->create([
            'driver' => StoreWhatsappIntegration::DRIVER_WHATS360,
            'credentials' => ['token' => 'tok_' . str_repeat('x', 20), 'instance_id' => 'device_abc123', ...$credentials],
            'is_active' => true,
            'auto_send' => true,
            'message_template' => 'أهلاً {name}، طلبك #{number}. رد ١ أو ٢.',
            'webhook_token' => StoreWhatsappIntegration::newToken(),
            'verify_token' => StoreWhatsappIntegration::newToken(),
            'connected_at' => now(),
        ]);
    }

    private function order(): Order
    {
        $order = Order::factory()->for($this->store)->create([
            'status' => Order::STATUS_PENDING,
            'customer_name' => 'سارة عبد الله',
            'customer_phone' => '01223344556',
            'total' => 450,
        ]);

        $order->items()->create([
            'name' => 'قميص قطن', 'unit_price' => 450, 'quantity' => 1, 'total' => 450,
        ]);

        return $order;
    }

    public function test_it_sends_in_the_shape_whats360_documents(): void
    {
        $link = $this->link();
        $order = $this->order();

        Http::fake(['whats360.live/api/v1/send' => Http::response(['success' => true, 'message_id' => '3EB0C4AA'])]);

        $result = app(WhatsappSender::class)->sendConfirmation($link, $order);

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://whats360.live/api/v1/send'
                && $data['token'] === 'tok_' . str_repeat('x', 20)
                && $data['instance_id'] === 'device_abc123'
                // Their own format — not the `@c.us` the other gateway uses.
                && $data['jid'] === '201223344556@s.whatsapp.net'
                && str_contains($data['message'], 'سارة عبد الله');
        });

        $this->assertDatabaseHas('whatsapp_messages', [
            'order_id' => $order->id,
            'provider_message_id' => '3EB0C4AA',
            'status' => 'sent',
        ]);
    }

    /**
     * The failure a status code hides: HTTP 200, `success: false`. The call
     * worked; the phone was offline. Reading only the code would mark the order
     * sent and leave the merchant waiting for a reply to nothing.
     */
    public function test_a_two_hundred_that_says_it_failed_is_a_failure(): void
    {
        $link = $this->link();
        $order = $this->order();

        Http::fake(['*/api/v1/send' => Http::response(['success' => false, 'message' => 'Device not connected'])]);

        $result = app(WhatsappSender::class)->sendConfirmation($link, $order);

        $this->assertFalse($result['ok']);
        $this->assertSame('failed', $order->fresh()->whatsapp_state);
        $this->assertSame('Device not connected', $order->fresh()->whatsapp_error);
    }

    public function test_a_rejected_token_is_named_as_such(): void
    {
        $link = $this->link();
        $order = $this->order();

        Http::fake(['*/api/v1/send' => Http::response(['message' => 'Invalid token'], 200)]);

        $result = app(WhatsappSender::class)->sendConfirmation($link, $order);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('رفض التوكن', (string) $result['error']);
    }

    public function test_the_merchant_can_point_it_at_another_host(): void
    {
        $link = $this->link(['base_url' => 'https://apis.whats360.live']);
        $order = $this->order();

        Http::fake(['apis.whats360.live/*' => Http::response(['success' => true, 'message_id' => 'X1'])]);

        app(WhatsappSender::class)->sendConfirmation($link, $order);

        Http::assertSent(fn ($request) => $request->url() === 'https://apis.whats360.live/api/v1/send');
    }

    /** Checking the keys must not message anybody. */
    public function test_verifying_sends_no_real_message(): void
    {
        $link = $this->link();

        Http::fake(['*/api/v1/send' => Http::response(['success' => false, 'message' => 'jid is required'], 422)]);

        $result = (new Whats360Driver($link))->verify();

        $this->assertTrue($result['ok']);
        Http::assertSent(fn ($request) => $request->data()['jid'] === '' && $request->data()['message'] === '');
    }

    public function test_verifying_fails_on_a_bad_token(): void
    {
        $link = $this->link();

        Http::fake(['*/api/v1/send' => Http::response(['message' => 'Unauthorized'], 401)]);

        $this->assertFalse((new Whats360Driver($link))->verify()['ok']);
    }

    public function test_a_wrong_host_is_reported_as_a_wrong_host(): void
    {
        $link = $this->link(['base_url' => 'https://example.com']);

        Http::fake(['*' => Http::response('Not Found', 404)]);

        $result = (new Whats360Driver($link))->verify();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('رابط الخدمة', $result['message']);
    }

    // ── Replies ─────────────────────────────────────────────────────────────

    public function test_it_reads_a_reply_in_their_jid_format(): void
    {
        $link = $this->link();
        $order = $this->order();
        $order->forceFill(['whatsapp_state' => 'sent'])->save();

        $this->postJson("/api/integrations/whatsapp/webhook/{$link->webhook_token}", [
            'data' => [
                'jid' => '201223344556@s.whatsapp.net',
                'message' => 'تمام',
                'message_id' => '3EB0IN',
            ],
        ])->assertOk();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    /**
     * Our own message ends with «ردّ بـ ١ … أو ٢». If the gateway echoes it
     * back and we read it, every order confirms itself.
     */
    public function test_it_ignores_the_echo_of_our_own_message(): void
    {
        $link = $this->link();
        $order = $this->order();
        $order->forceFill(['whatsapp_state' => 'sent'])->save();

        $this->postJson("/api/integrations/whatsapp/webhook/{$link->webhook_token}", [
            'data' => [
                'jid' => '201223344556@s.whatsapp.net',
                'message' => 'أهلاً سارة، طلبك #1. رد ١ أو ٢.',
                'from_me' => true,
            ],
        ])->assertOk();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    // ── Settings ────────────────────────────────────────────────────────────

    public function test_a_merchant_can_connect_it_from_the_settings_screen(): void
    {
        Http::fake(['*/api/v1/send' => Http::response(['success' => false, 'message' => 'jid is required'], 422)]);

        $this->actingAs($this->user)
            ->put('http://localhost/settings/whatsapp', [
                'driver' => 'whats360',
                'token' => 'tok_' . str_repeat('a', 20),
                'instance_id' => 'device_xyz',
            ])
            ->assertSessionHas('status', 'whatsapp-connected');

        $link = $this->store->fresh()->whatsappIntegration;

        $this->assertSame('whats360', $link->driver);
        $this->assertSame('device_xyz', $link->credential('instance_id'));
        $this->assertTrue($link->canSend());
    }

    public function test_it_asks_for_the_instance_id(): void
    {
        $this->actingAs($this->user)
            ->put('http://localhost/settings/whatsapp', [
                'driver' => 'whats360',
                'token' => 'tok_' . str_repeat('a', 20),
            ])
            ->assertSessionHasErrors('instance_id');
    }
}

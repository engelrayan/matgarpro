<?php

namespace Tests\Feature\Whatsapp;

use App\Jobs\SendWhatsappOrderConfirmation;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreWhatsappIntegration;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsappConfirmationTest extends TestCase
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

    private function link(array $attributes = []): StoreWhatsappIntegration
    {
        return $this->store->whatsappIntegration()->create([
            'driver' => StoreWhatsappIntegration::DRIVER_WAPILOT,
            'credentials' => ['token' => 'tok_' . str_repeat('x', 20), 'instance' => 'instance3853'],
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

    private function reply(StoreWhatsappIntegration $link, string $body, string $from = '201223344556'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/integrations/whatsapp/webhook/{$link->webhook_token}", [
            'messages' => [[
                'from' => $from . '@c.us',
                'body' => $body,
                'id' => 'wamid.' . md5($body),
            ]],
        ]);
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

    public function test_connecting_checks_the_keys_against_the_gateway(): void
    {
        Http::fake(['*/status' => Http::response(['status' => 'authenticated'])]);

        $this->actingAs($this->user)
            ->put('http://localhost/settings/whatsapp', [
                'driver' => 'wapilot',
                'token' => 'tok_' . str_repeat('a', 20),
                'instance' => 'instance3853',
            ])
            ->assertSessionHas('status', 'whatsapp-connected');

        $link = $this->store->fresh()->whatsappIntegration;

        $this->assertSame('wapilot', $link->driver);
        $this->assertSame('instance3853', $link->credential('instance'));
        // Encrypted at rest — the column must not hold the token in clear.
        $this->assertStringNotContainsString('tok_', $link->getRawOriginal('credentials'));
    }

    public function test_a_rejected_token_is_not_saved(): void
    {
        Http::fake(['*/status' => Http::response(['message' => 'Unauthorized'], 401)]);

        $this->actingAs($this->user)
            ->put('http://localhost/settings/whatsapp', [
                'driver' => 'wapilot',
                'token' => 'tok_' . str_repeat('b', 20),
                'instance' => 'instance1',
            ])
            ->assertSessionHasErrors('driver');

        $this->assertNull($this->store->fresh()->whatsappIntegration);
    }

    // ── Sending ─────────────────────────────────────────────────────────────

    public function test_it_messages_the_customer_with_the_order_in_it(): void
    {
        $link = $this->link(['message_template' => 'أهلاً {name}، طلبك #{number} بـ {total} {currency}. رد ١ أو ٢.']);
        $order = $this->order();

        Http::fake(['*/send-message' => Http::response(['id' => 'wamid.OUT1'])]);

        (new SendWhatsappOrderConfirmation($order->id))->handle(app(\App\Services\Whatsapp\WhatsappSender::class));

        Http::assertSent(function ($request) {
            $body = (string) data_get($request->data(), 'text');

            return data_get($request->data(), 'chat_id') === '201223344556@c.us'
                && str_contains($body, 'سارة عبد الله')
                && str_contains($body, '450.00');
        });

        $order->refresh();

        $this->assertSame('sent', $order->whatsapp_state);
        $this->assertNotNull($order->whatsapp_sent_at);
        $this->assertDatabaseHas('whatsapp_messages', [
            'order_id' => $order->id,
            'direction' => 'out',
            'status' => 'sent',
            'provider_message_id' => 'wamid.OUT1',
        ]);
    }

    public function test_a_refused_message_is_recorded_on_the_order(): void
    {
        $link = $this->link();
        $order = $this->order();

        Http::fake(['*/send-message' => Http::response(['message' => 'Session closed'], 400)]);

        (new SendWhatsappOrderConfirmation($order->id))->handle(app(\App\Services\Whatsapp\WhatsappSender::class));

        $order->refresh();

        $this->assertSame('failed', $order->whatsapp_state);
        $this->assertSame('Session closed', $order->whatsapp_error);
        // The customer was never told anything, so the order stays where it was.
        $this->assertSame(Order::STATUS_PENDING, $order->status);
    }

    public function test_a_customer_is_not_messaged_twice_about_the_same_order(): void
    {
        $this->link();
        $order = $this->order(['whatsapp_state' => 'sent', 'whatsapp_sent_at' => now()]);

        Http::fake();

        (new SendWhatsappOrderConfirmation($order->id))->handle(app(\App\Services\Whatsapp\WhatsappSender::class));

        Http::assertNothingSent();
    }

    // ── Replies ─────────────────────────────────────────────────────────────

    public function test_a_yes_confirms_the_order(): void
    {
        $link = $this->link();
        $order = $this->order(['whatsapp_state' => 'sent', 'whatsapp_sent_at' => now()]);

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
        $order = $this->order([
            'customer_phone' => '01223344556',
            'whatsapp_state' => 'sent',
        ]);

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

        $this->postJson("/api/integrations/whatsapp/webhook/{$link->webhook_token}", [
            'messages' => [[
                'from' => '201223344556@c.us',
                'from_me' => true,
                'body' => 'تمام',
            ]],
        ])->assertOk();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_an_unknown_webhook_token_is_refused(): void
    {
        $this->postJson('/api/integrations/whatsapp/webhook/' . str_repeat('z', 40), [])
            ->assertStatus(404);
    }

    // ── Cloud API ───────────────────────────────────────────────────────────

    public function test_meta_gets_its_challenge_echoed_back(): void
    {
        $link = $this->link([
            'driver' => StoreWhatsappIntegration::DRIVER_CLOUD_API,
            'credentials' => ['access_token' => 'EAA' . str_repeat('x', 30), 'phone_number_id' => '1234567890'],
        ]);

        $this->get("/api/integrations/whatsapp/webhook/{$link->webhook_token}?hub_mode=subscribe"
            . "&hub_verify_token={$link->verify_token}&hub_challenge=42")
            ->assertOk()
            ->assertSee('42');
    }

    public function test_a_wrong_verify_token_is_refused(): void
    {
        $link = $this->link([
            'driver' => StoreWhatsappIntegration::DRIVER_CLOUD_API,
            'credentials' => ['access_token' => 'EAA' . str_repeat('x', 30), 'phone_number_id' => '1234567890'],
        ]);

        $this->get("/api/integrations/whatsapp/webhook/{$link->webhook_token}"
            . '?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=42')
            ->assertStatus(403);
    }

    /**
     * A tapped button, in Meta's own shape. The title is what the customer saw,
     * so that is what the reply reader gets.
     */
    public function test_it_reads_a_tapped_button_from_meta(): void
    {
        $link = $this->link([
            'driver' => StoreWhatsappIntegration::DRIVER_CLOUD_API,
            'credentials' => ['access_token' => 'EAA' . str_repeat('x', 30), 'phone_number_id' => '1234567890'],
        ]);
        $order = $this->order(['whatsapp_state' => 'sent']);

        $this->postJson("/api/integrations/whatsapp/webhook/{$link->webhook_token}", [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '201223344556',
                            'id' => 'wamid.IN1',
                            'type' => 'button',
                            'button' => ['text' => 'أكّد الطلب', 'payload' => 'confirm'],
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    public function test_a_forged_reply_is_refused_when_the_app_secret_is_set(): void
    {
        $link = $this->link([
            'driver' => StoreWhatsappIntegration::DRIVER_CLOUD_API,
            'credentials' => [
                'access_token' => 'EAA' . str_repeat('x', 30),
                'phone_number_id' => '1234567890',
                'app_secret' => 'shhh',
            ],
        ]);
        $order = $this->order(['whatsapp_state' => 'sent']);

        $this->postJson("/api/integrations/whatsapp/webhook/{$link->webhook_token}", [
            'entry' => [['changes' => [['value' => ['messages' => [[
                'from' => '201223344556', 'type' => 'text', 'text' => ['body' => 'لا'],
            ]]]]]]],
        ])->assertStatus(401);

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }
}

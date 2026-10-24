<?php

namespace Tests\Feature\Whatsapp;

use App\Jobs\SendWhatsappOrderConfirmation;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreWhatsappIntegration;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\Whatsapp\ButtonPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The platform's own WhatsApp number: one click for the merchant, a button for
 * the customer, and an order that confirms itself.
 *
 * One number carries every store's customers, so most of what is tested here
 * is about a tap reaching the right order and nothing else — not one shop's
 * customer confirming another shop's parcel.
 */
class PlatformWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'platform-app-secret';

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.whatsapp.platform' => [
            'access_token' => 'platform-token',
            'phone_number_id' => '111222333',
            'business_account_id' => '999888777',
            'app_secret' => self::SECRET,
            'verify_token' => 'platform-verify',
            'template' => 'order_confirmation',
            'template_language' => 'ar',
            'remind_after_hours' => 3,
            'give_up_after_hours' => 12,
        ]]);

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT1']]])]);

        $this->user = User::factory()->create();
        $this->store = Store::factory()->for($this->user)->create(['name' => 'متجر الأناقة']);
    }

    private function onPlatform(?Store $store = null): StoreWhatsappIntegration
    {
        return ($store ?? $this->store)->whatsappIntegration()->create([
            'driver' => StoreWhatsappIntegration::DRIVER_PLATFORM,
            'credentials' => [],
            'is_active' => true,
            'auto_send' => true,
            'webhook_token' => StoreWhatsappIntegration::newToken(),
            'verify_token' => StoreWhatsappIntegration::newToken(),
            'connected_at' => now(),
        ]);
    }

    private function order(array $attributes = [], ?Store $store = null): Order
    {
        $order = Order::factory()->for($store ?? $this->store)->create([
            'status' => Order::STATUS_PENDING,
            'customer_name' => 'سارة',
            'customer_phone' => '01223344556',
            'total' => 450,
            ...$attributes,
        ]);

        $order->items()->create(['name' => 'قميص قطن', 'unit_price' => 450, 'quantity' => 1, 'total' => 450]);

        return $order;
    }

    /** An order we have already messaged, waiting on the customer. */
    private function awaiting(array $attributes = [], ?Store $store = null): Order
    {
        return $this->order(['whatsapp_state' => 'sent', 'whatsapp_sent_at' => now(), ...$attributes], $store);
    }

    /** POST a Meta-shaped webhook, signed the way Meta signs it. */
    private function webhook(array $message, ?string $secret = self::SECRET): \Illuminate\Testing\TestResponse
    {
        $json = json_encode(['entry' => [['changes' => [['value' => ['messages' => [[
            'from' => '201223344556',
            'id' => 'wamid.IN' . uniqid(),
            ...$message,
        ]]]]]]]], JSON_UNESCAPED_UNICODE);

        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        if ($secret !== null) {
            $server['HTTP_X_HUB_SIGNATURE_256'] = 'sha256=' . hash_hmac('sha256', $json, $secret);
        }

        return $this->call('POST', '/api/integrations/whatsapp/platform', [], [], [], $server, $json);
    }

    private function tap(Order $order, string $intent, string $from = '201223344556'): \Illuminate\Testing\TestResponse
    {
        return $this->webhook([
            'from' => $from,
            'type' => 'button',
            'button' => ['text' => 'تأكيد الطلب', 'payload' => ButtonPayload::for($order, $intent)],
        ]);
    }

    // ── The merchant ────────────────────────────────────────────────────────

    public function test_a_merchant_switches_it_on_with_one_click(): void
    {
        $this->actingAs($this->user)
            ->post('http://localhost/settings/whatsapp/platform')
            ->assertSessionHas('status', 'whatsapp-platform');

        $link = $this->store->fresh()->whatsappIntegration;

        $this->assertSame(StoreWhatsappIntegration::DRIVER_PLATFORM, $link->driver);
        $this->assertTrue($link->canSend());
        $this->assertTrue($link->auto_send);
    }

    public function test_it_is_not_offered_until_the_platform_number_is_configured(): void
    {
        config(['services.whatsapp.platform.access_token' => null]);

        $this->actingAs($this->user)
            ->get('http://localhost/settings/whatsapp')
            ->assertInertia(fn ($page) => $page->where('platform.available', false));

        $this->actingAs($this->user)->post('http://localhost/settings/whatsapp/platform')->assertNotFound();
    }

    public function test_the_settings_page_previews_the_message_with_the_shops_name(): void
    {
        $this->onPlatform();

        $this->actingAs($this->user)
            ->get('http://localhost/settings/whatsapp')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('integration.driver', 'platform')
                ->where('platform.preview', fn (string $text) => str_contains($text, 'متجر الأناقة')));
    }

    // ── Sending ─────────────────────────────────────────────────────────────

    public function test_the_customer_gets_the_template_with_two_buttons_bound_to_the_order(): void
    {
        $this->onPlatform();
        $order = $this->order();

        SendWhatsappOrderConfirmation::dispatchSync($order->id);

        Http::assertSent(function ($request) use ($order) {
            $components = collect($request['template']['components'] ?? []);
            $body = $components->firstWhere('type', 'body');
            $buttons = $components->where('type', 'button')->values();

            return $request->url() === 'https://graph.facebook.com/' . config('services.whatsapp.cloud_api_version') . '/111222333/messages'
                && $request->hasHeader('Authorization', 'Bearer platform-token')
                && $request['template']['name'] === 'order_confirmation'
                // name, number, store, items, amount — the shop is named,
                // because the number is ours.
                && $body['parameters'][2]['text'] === 'متجر الأناقة'
                && $buttons[0]['parameters'][0]['payload'] === ButtonPayload::for($order, 'confirm')
                && $buttons[1]['parameters'][0]['payload'] === ButtonPayload::for($order, 'cancel');
        });

        $this->assertSame('sent', $order->fresh()->whatsapp_state);
        $this->assertDatabaseHas('whatsapp_messages', ['order_id' => $order->id, 'provider_message_id' => 'wamid.OUT1']);
    }

    // ── The tap ─────────────────────────────────────────────────────────────

    public function test_tapping_confirm_confirms_the_order_and_tells_the_customer(): void
    {
        $this->onPlatform();
        $order = $this->awaiting();

        $this->tap($order, 'confirm')->assertOk();

        $order->refresh();
        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertSame('confirmed', $order->whatsapp_state);

        // The receipt, as free text inside the window the tap just opened.
        Http::assertSent(fn ($request) => ($request['type'] ?? null) === 'text'
            && str_contains($request['text']['body'], 'اتأكد')
            && str_contains($request['text']['body'], 'متجر الأناقة'));
    }

    public function test_tapping_cancel_cancels_it(): void
    {
        $this->onPlatform();
        $order = $this->awaiting();

        $this->tap($order, 'cancel')->assertOk();

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_an_unsigned_webhook_is_refused(): void
    {
        $this->onPlatform();
        $order = $this->awaiting();

        $this->webhook([
            'type' => 'button',
            'button' => ['text' => 'تأكيد الطلب', 'payload' => ButtonPayload::for($order, 'confirm')],
        ], secret: null)->assertUnauthorized();

        $this->webhook(['type' => 'text', 'text' => ['body' => 'تمام']], secret: 'not-the-secret')->assertUnauthorized();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_nothing_is_accepted_while_no_app_secret_is_configured(): void
    {
        $this->onPlatform();
        $order = $this->awaiting();
        config(['services.whatsapp.platform.app_secret' => null]);

        $this->tap($order, 'confirm')->assertUnauthorized();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_a_tap_only_ever_moves_the_order_on_its_button(): void
    {
        $this->onPlatform();
        $other = Store::factory()->create();
        $this->onPlatform($other);

        $mine = $this->awaiting();
        $theirs = $this->awaiting([], $other);

        $this->tap($mine, 'confirm')->assertOk();

        $this->assertSame(Order::STATUS_CONFIRMED, $mine->fresh()->status);
        $this->assertSame(Order::STATUS_PENDING, $theirs->fresh()->status);
    }

    public function test_a_button_from_someone_else_moves_nothing(): void
    {
        $this->onPlatform();
        $order = $this->awaiting();

        // The right payload, from a number that is not the customer's.
        $this->tap($order, 'confirm', from: '201099887766')->assertOk();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_a_forged_payload_is_not_read_as_a_button(): void
    {
        $this->assertNull(ButtonPayload::read('confirm:1234:not-our-signature'));
        $this->assertNull(ButtonPayload::read('ship:1234:' . str_repeat('a', 20)));
        $this->assertNull(ButtonPayload::read(null));
    }

    public function test_a_late_tap_still_confirms_an_order_on_the_call_list(): void
    {
        $this->onPlatform();
        $order = $this->awaiting(['whatsapp_state' => 'no_reply']);

        $this->tap($order, 'confirm')->assertOk();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    public function test_a_second_tap_after_the_merchant_decided_changes_nothing(): void
    {
        $this->onPlatform();
        $order = $this->awaiting(['status' => Order::STATUS_CONFIRMED]);

        $this->tap($order, 'cancel')->assertOk();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    // ── Typed replies ───────────────────────────────────────────────────────

    public function test_a_typed_reply_quoting_our_message_reaches_its_order(): void
    {
        $this->onPlatform();
        $order = $this->awaiting();
        WhatsappMessage::create([
            'store_id' => $this->store->id, 'order_id' => $order->id, 'direction' => 'out',
            'phone' => '201223344556', 'provider_message_id' => 'wamid.OURS', 'status' => 'sent',
        ]);

        $this->webhook(['type' => 'text', 'text' => ['body' => 'تمام يا فندم'], 'context' => ['id' => 'wamid.OURS']])->assertOk();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }

    /**
     * One customer, two shops, one shared number. «تمام» could be about
     * either, and guessing ships a parcel somebody did not agree to.
     */
    public function test_an_ambiguous_typed_reply_moves_nothing(): void
    {
        $this->onPlatform();
        $other = Store::factory()->create();
        $this->onPlatform($other);

        $mine = $this->awaiting();
        $theirs = $this->awaiting([], $other);

        $this->webhook(['type' => 'text', 'text' => ['body' => 'تمام']])->assertOk();

        $this->assertSame(Order::STATUS_PENDING, $mine->fresh()->status);
        $this->assertSame(Order::STATUS_PENDING, $theirs->fresh()->status);
    }

    public function test_a_typed_reply_with_one_waiting_order_is_read_as_usual(): void
    {
        $this->onPlatform();
        $order = $this->awaiting();

        $this->webhook(['type' => 'text', 'text' => ['body' => 'لا مش عايز']])->assertOk();

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
    }

    // ── Reminders ───────────────────────────────────────────────────────────

    public function test_a_silent_customer_is_reminded_once_then_handed_to_the_merchant(): void
    {
        // Midday in Cairo, well outside the quiet hours.
        Carbon::setTestNow(Carbon::parse('2026-10-25 12:00', 'Africa/Cairo'));

        $this->onPlatform();
        $order = $this->awaiting(['whatsapp_sent_at' => now()->subHours(4)]);
        $fresh = $this->awaiting(['whatsapp_sent_at' => now()->subHour(), 'customer_phone' => '01111111111']);

        $this->artisan('whatsapp:remind')->assertSuccessful();

        $this->assertNotNull($order->fresh()->whatsapp_reminded_at);
        $this->assertNull($fresh->fresh()->whatsapp_reminded_at, 'too early for a reminder');

        // Running again does not message them a third time.
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OUT2']]])]);
        $this->artisan('whatsapp:remind');
        Http::assertNothingSent();

        Carbon::setTestNow(now()->addHours(13));
        $this->artisan('whatsapp:remind');

        $this->assertSame('no_reply', $order->fresh()->whatsapp_state);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status, 'the merchant decides, not the timer');

        Carbon::setTestNow();
    }

    public function test_nobody_is_reminded_in_the_middle_of_the_night(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-25 02:00', 'Africa/Cairo'));

        $this->onPlatform();
        $order = $this->awaiting(['whatsapp_sent_at' => now()->subHours(5)]);

        $this->artisan('whatsapp:remind');

        $this->assertNull($order->fresh()->whatsapp_reminded_at);

        Carbon::setTestNow();
    }

    public function test_a_store_on_its_own_gateway_is_never_reminded(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-25 12:00', 'Africa/Cairo'));

        $this->store->whatsappIntegration()->create([
            'driver' => StoreWhatsappIntegration::DRIVER_WAPILOT,
            'credentials' => ['token' => 'tok_' . str_repeat('x', 20), 'instance' => 'instance1'],
            'is_active' => true, 'auto_send' => true,
            'webhook_token' => StoreWhatsappIntegration::newToken(),
            'verify_token' => StoreWhatsappIntegration::newToken(),
        ]);
        $order = $this->awaiting(['whatsapp_sent_at' => now()->subHours(5)]);

        $this->artisan('whatsapp:remind');

        $this->assertNull($order->fresh()->whatsapp_reminded_at);

        Carbon::setTestNow();
    }

    // ── Setup ───────────────────────────────────────────────────────────────

    public function test_meta_gets_its_challenge_back_on_the_platform_webhook(): void
    {
        $this->get('/api/integrations/whatsapp/platform?hub_mode=subscribe&hub_verify_token=platform-verify&hub_challenge=42')
            ->assertOk()
            ->assertSee('42');

        $this->get('/api/integrations/whatsapp/platform?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=42')
            ->assertForbidden();
    }

    public function test_the_template_command_creates_exactly_what_the_code_fills_in(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => '1', 'status' => 'PENDING'])]);

        $this->artisan('whatsapp:platform-template')->assertSuccessful();

        Http::assertSent(function ($request) {
            $body = collect($request['components'])->firstWhere('type', 'BODY');
            $buttons = collect($request['components'])->firstWhere('type', 'BUTTONS')['buttons'];

            return str_ends_with($request->url(), '/999888777/message_templates')
                && $request['category'] === 'UTILITY'
                // Five slots, in the order the sender fills them.
                && str_contains($body['text'], '{{5}}') && ! str_contains($body['text'], '{{6}}')
                && count($buttons) === 2
                && $buttons[0]['type'] === 'QUICK_REPLY';
        });
    }
}

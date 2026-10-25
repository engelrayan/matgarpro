<?php

namespace Tests\Feature\Whatsapp;

use App\Jobs\RunAiGeneration;
use App\Models\AiGeneration;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreWhatsappIntegration;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'waha-webhook-secret';

    private User $user;

    private Store $store;

    private StoreWhatsappIntegration $link;

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

        $this->user = User::factory()->create(['name' => 'منى']);
        $this->store = Store::factory()->for($this->user)->create(['name' => 'متجر الأناقة']);
        $this->link = $this->store->whatsappIntegration()->create([
            'driver' => StoreWhatsappIntegration::DRIVER_WAHA,
            'credentials' => ['status' => 'WORKING'],
            'is_active' => true,
            'auto_send' => true,
            'webhook_token' => StoreWhatsappIntegration::newToken(),
            'verify_token' => StoreWhatsappIntegration::newToken(),
        ]);
    }

    private function webhook(array $body): \Illuminate\Testing\TestResponse
    {
        $json = json_encode(['session' => 'store-' . $this->store->id, ...$body], JSON_UNESCAPED_UNICODE);

        return $this->call('POST', "/api/integrations/whatsapp/webhook/{$this->link->webhook_token}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_HMAC' => hash_hmac('sha512', $json, self::SECRET),
        ], $json);
    }

    private function incoming(string $body, string $id = 'false_201223344556@c.us_AAA', array $extra = []): array
    {
        return ['event' => 'message.any', 'payload' => [
            'id' => $id, 'from' => '201223344556@c.us', 'fromMe' => false, 'body' => $body,
            '_data' => ['Info' => ['PushName' => 'Sara W']], ...$extra,
        ]];
    }

    private function conversation(): WhatsappConversation
    {
        return WhatsappConversation::where('store_id', $this->store->id)->firstOrFail();
    }

    public function test_a_customer_message_opens_an_unread_conversation(): void
    {
        $this->webhook($this->incoming('عندكم مقاس L؟'))->assertOk();

        $conversation = $this->conversation();
        $this->assertSame('201223344556', $conversation->phone);
        $this->assertSame('Sara W', $conversation->name);
        $this->assertSame(1, $conversation->unread_count);
        $this->assertSame('عندكم مقاس L؟', $conversation->last_preview);

        $this->actingAs($this->user)->get('/inbox')->assertOk()
            ->assertInertia(fn ($page) => $page->component('inbox/Index')
                ->where('conversations.0.name', 'Sara W')
                ->where('conversations.0.unread', 1)
                ->where('inbox_unread', 1));
    }

    public function test_the_name_on_the_customers_order_wins_over_their_whatsapp_name(): void
    {
        Order::factory()->for($this->store)->create(['customer_name' => 'سارة محمد', 'customer_phone' => '01223344556']);

        $this->webhook($this->incoming('السلام عليكم'));

        $this->assertSame('سارة محمد', $this->conversation()->name);
    }

    public function test_opening_a_conversation_marks_it_read_and_shows_the_customers_orders(): void
    {
        $order = Order::factory()->for($this->store)->create(['customer_phone' => '01223344556', 'status' => Order::STATUS_PENDING]);
        $this->webhook($this->incoming('فين طلبي؟'));
        $conversation = $this->conversation();

        $this->actingAs($this->user)->getJson("/inbox/{$conversation->id}")->assertOk()
            ->assertJsonPath('messages.0.body', 'فين طلبي؟')
            ->assertJsonPath('orders.0.id', $order->id);

        $this->assertSame(0, $conversation->fresh()->unread_count);
    }

    public function test_a_reply_goes_out_on_the_stores_number_and_says_who_wrote_it(): void
    {
        $this->webhook($this->incoming('عندكم مقاس L؟'));
        Http::fake(['waha.test/api/sendText' => Http::response(['id' => 'true_201223344556@c.us_BBB'])]);

        $this->actingAs($this->user)
            ->postJson("/inbox/{$this->conversation()->id}/messages", ['body' => 'أيوه متوفر 👌'])
            ->assertCreated()
            ->assertJsonPath('message.by', 'منى');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/sendText')
            && $r['chatId'] === '201223344556@c.us' && $r['text'] === 'أيوه متوفر 👌');
        $this->assertSame('out', $this->conversation()->last_direction);
    }

    public function test_our_own_send_coming_back_from_waha_is_not_shown_twice(): void
    {
        $this->webhook($this->incoming('عندكم مقاس L؟'));
        Http::fake(['waha.test/api/sendText' => Http::response(['id' => 'true_201223344556@c.us_BBB'])]);
        $this->actingAs($this->user)->postJson("/inbox/{$this->conversation()->id}/messages", ['body' => 'أيوه']);

        // WAHA echoes it, the chat named by its hidden id this time.
        $this->webhook(['event' => 'message.any', 'payload' => [
            'id' => 'true_95589446484048@lid_BBB', 'from' => '201000000001@c.us', 'to' => '95589446484048@lid', 'fromMe' => true, 'body' => 'أيوه',
            '_data' => ['Info' => ['RecipientAlt' => '201223344556@s.whatsapp.net']],
        ]]);

        $this->assertSame(1, WhatsappMessage::where('direction', 'out')->count());
    }

    public function test_what_the_merchant_types_on_the_phone_joins_the_conversation(): void
    {
        $this->webhook(['event' => 'message.any', 'payload' => [
            'id' => 'true_201223344556@c.us_CCC', 'from' => '201000000001@c.us', 'to' => '201223344556@c.us', 'fromMe' => true, 'body' => 'هنبعتلك اللينك',
        ]])->assertOk();

        $message = WhatsappMessage::sole();
        $this->assertSame('out', $message->direction);
        $this->assertSame('phone', $message->meta['source']);
        $this->assertSame(0, $this->conversation()->unread_count);
    }

    public function test_a_photo_is_kept_as_a_line_and_confirms_nothing(): void
    {
        Order::factory()->for($this->store)->create([
            'customer_phone' => '01223344556', 'status' => Order::STATUS_PENDING, 'whatsapp_state' => 'sent',
        ]);

        $this->webhook($this->incoming('', extra: ['hasMedia' => true, '_data' => ['Info' => ['MediaType' => 'image']]]));

        $message = WhatsappMessage::sole();
        $this->assertSame(WhatsappMessage::KIND_MEDIA, $message->kind);
        $this->assertSame('📷 صورة', $message->body);
        $this->assertSame(Order::STATUS_PENDING, Order::sole()->status);
    }

    public function test_delivery_and_read_ticks_only_move_forward(): void
    {
        $this->webhook($this->incoming('عندكم مقاس L؟'));
        Http::fake(['waha.test/api/sendText' => Http::response(['id' => 'true_201223344556@c.us_BBB'])]);
        $this->actingAs($this->user)->postJson("/inbox/{$this->conversation()->id}/messages", ['body' => 'أيوه']);

        $this->webhook(['event' => 'message.ack', 'payload' => ['id' => 'true_95589446484048@lid_BBB', 'ack' => 3]]);
        $this->webhook(['event' => 'message.ack', 'payload' => ['id' => 'true_95589446484048@lid_BBB', 'ack' => 2]]);

        $this->assertSame('read', WhatsappMessage::where('direction', 'out')->value('status'));
    }

    public function test_notes_tags_and_ready_replies(): void
    {
        $this->webhook($this->incoming('سلام'));
        $id = $this->conversation()->id;

        $this->actingAs($this->user)->postJson("/inbox/{$id}/notes", ['body' => 'عميل VIP'])->assertCreated();
        $this->actingAs($this->user)->putJson("/inbox/{$id}/tags", ['tags' => ['مهم', ' مهم ', 'متابعة']])
            ->assertJsonPath('tags', ['مهم', 'متابعة']);
        $this->actingAs($this->user)->postJson('/inbox/quick-replies', ['title' => 'الشحن', 'body' => 'الشحن ٢-٣ أيام'])
            ->assertCreated()->assertJsonPath('quickReplies.0.title', 'الشحن');

        $this->actingAs($this->user)->getJson("/inbox/{$id}")->assertJsonPath('notes.0.body', 'عميل VIP');
        // A note is the team's: never a message, never sent.
        $this->assertSame(0, WhatsappMessage::where('direction', 'out')->count());
    }

    public function test_an_order_is_confirmed_from_the_chat(): void
    {
        $order = Order::factory()->for($this->store)->create(['customer_phone' => '01223344556', 'status' => Order::STATUS_PENDING]);
        $this->webhook($this->incoming('أكدلي الطلب'));

        $this->actingAs($this->user)
            ->patchJson("/inbox/{$this->conversation()->id}/orders/{$order->id}", ['status' => Order::STATUS_CONFIRMED])
            ->assertOk()->assertJsonPath('orders.0.status', Order::STATUS_CONFIRMED);
    }

    public function test_another_customers_order_cannot_be_changed_through_a_conversation(): void
    {
        $other = Order::factory()->for($this->store)->create(['customer_phone' => '01099998888', 'status' => Order::STATUS_PENDING]);
        $this->webhook($this->incoming('سلام'));

        $this->actingAs($this->user)
            ->patchJson("/inbox/{$this->conversation()->id}/orders/{$other->id}", ['status' => Order::STATUS_CANCELLED])
            ->assertNotFound();
    }

    public function test_another_stores_conversation_is_not_found(): void
    {
        $this->webhook($this->incoming('سلام'));
        $stranger = User::factory()->create();
        Store::factory()->for($stranger)->create();

        $this->actingAs($stranger)->getJson("/inbox/{$this->conversation()->id}")->assertNotFound();
        $this->actingAs($stranger)->postJson("/inbox/{$this->conversation()->id}/messages", ['body' => 'x'])->assertNotFound();
    }

    public function test_the_platform_number_cannot_be_answered_from_the_inbox(): void
    {
        $this->link->update(['driver' => StoreWhatsappIntegration::DRIVER_PLATFORM]);
        $conversation = WhatsappConversation::resolve($this->store->id, '201223344556');

        $this->actingAs($this->user)->get('/inbox')->assertInertia(fn ($page) => $page->where('channel.can_reply', false));
        $this->actingAs($this->user)->postJson("/inbox/{$conversation->id}/messages", ['body' => 'أهلاً'])->assertStatus(422);
    }

    public function test_the_assistant_summarises_and_suggests_a_reply(): void
    {
        $this->webhook($this->incoming('عندكم الجاكيت الأسود مقاس L؟'));
        $conversation = $this->conversation();

        Http::fake(['api.anthropic.com/*' => fn () => Http::response([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-haiku-5-5',
            'content' => [['type' => 'text', 'text' => json_encode(['summary' => 'عميلة جديدة بتسأل عن مقاس.', 'reply' => 'أيوه متوفر يا فندم 👌'], JSON_UNESCAPED_UNICODE)]],
            'stop_reason' => 'end_turn', 'stop_sequence' => null,
            'usage' => ['input_tokens' => 900, 'output_tokens' => 80, 'cache_creation_input_tokens' => 0, 'cache_read_input_tokens' => 0],
        ])]);

        $started = $this->actingAs($this->user)->postJson("/inbox/{$conversation->id}/ai")->assertAccepted()->json();
        RunAiGeneration::dispatchSync($started['id']);

        $this->actingAs($this->user)->getJson("/inbox/{$conversation->id}/ai/{$started['id']}")
            ->assertJsonPath('status', AiGeneration::STATUS_DONE)
            ->assertJsonPath('draft.reply', 'أيوه متوفر يا فندم 👌');

        $this->assertSame('عميلة جديدة بتسأل عن مقاس.', $conversation->fresh()->summary);
        Http::assertSent(fn ($r) => str_contains((string) $r->body(), 'عندكم الجاكيت الأسود مقاس L؟'));
    }
}

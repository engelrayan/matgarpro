<?php

namespace Tests\Feature\Daman;

use App\Models\Order;
use App\Models\Store;
use App\Models\StoreDamanIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DamanShippingTest extends TestCase
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

    private function link(array $attributes = []): StoreDamanIntegration
    {
        return $this->store->damanIntegration()->create([
            'api_key' => 'dm_live_' . str_repeat('k', 32),
            'key_prefix' => 'dm_live_kkkkkkkk',
            'environment' => 'live',
            'is_active' => true,
            'webhook_token' => StoreDamanIntegration::newWebhookToken(),
            'webhook_secret' => 'whsec_' . str_repeat('s', 40),
            'connected_at' => now(),
            ...$attributes,
        ]);
    }

    private function order(array $attributes = []): Order
    {
        return Order::factory()->for($this->store)->create([
            'status' => Order::STATUS_CONFIRMED,
            'customer_name' => 'سارة عبد الله',
            'customer_phone' => '01223344556',
            'governorate' => 'الإسكندرية',
            'city' => 'سموحة',
            'address' => 'شارع فوزي معاذ، عمارة ١٢',
            'subtotal' => 400,
            'shipping_amount' => 50,
            'total' => 450,
            ...$attributes,
        ]);
    }

    /** One created shipment, in the envelope Daman's bulk endpoint returns. */
    private function damanCreated(array $overrides = []): array
    {
        return [
            'data' => [
                'environment' => 'live',
                'total' => 1,
                'created' => 1,
                'failed' => 0,
                'results' => [[
                    'status' => 'created',
                    'shipment' => [
                        'id' => 9001,
                        'daman_order_number' => 'DM-2026-0001',
                        'tracking_number' => 'SNB-778899',
                        'status' => 'in_delivery',
                        'shipping_company' => ['id' => 3, 'name' => 'سندباد إكسبريس'],
                        ...$overrides,
                    ],
                ]],
            ],
        ];
    }

    // ── Settings ────────────────────────────────────────────────────────────

    /**
     * Every settings screen gets one of these — a missing controller import is
     * invisible until somebody opens the page.
     */
    public function test_the_page_renders(): void
    {
        $this->actingAs($this->user)
            ->get('http://localhost/settings/daman')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Daman')
                ->where('integration.connected', false));
    }

    public function test_connecting_checks_the_key_against_daman_before_saving(): void
    {
        Http::fake(['*/governorates' => Http::response(['data' => []])]);

        $this->actingAs($this->user)
            ->put('http://localhost/settings/daman', ['api_key' => 'dm_live_' . str_repeat('a', 32)])
            ->assertSessionHas('status', 'daman-connected');

        $link = $this->store->fresh()->damanIntegration;

        $this->assertSame('live', $link->environment);
        $this->assertSame('dm_live_aaaaaaaa', $link->key_prefix);
        // Encrypted at rest — the column must not hold the key in clear.
        $this->assertNotSame('dm_live_' . str_repeat('a', 32), $link->getRawOriginal('api_key'));
    }

    public function test_a_rejected_key_is_not_saved(): void
    {
        Http::fake(['*/governorates' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $this->actingAs($this->user)
            ->put('http://localhost/settings/daman', ['api_key' => 'dm_live_' . str_repeat('b', 32)])
            ->assertSessionHasErrors('api_key');

        $this->assertNull($this->store->fresh()->damanIntegration);
    }

    public function test_a_test_key_is_recognised_as_such(): void
    {
        Http::fake(['*/governorates' => Http::response(['data' => []])]);

        $this->actingAs($this->user)
            ->put('http://localhost/settings/daman', ['api_key' => 'dm_test_' . str_repeat('c', 32)]);

        $this->assertSame('test', $this->store->fresh()->damanIntegration->environment);
    }

    // ── Handing orders over ─────────────────────────────────────────────────

    public function test_shipping_stores_both_numbers_daman_answers_with(): void
    {
        $this->link();
        $order = $this->order();

        Http::fake(['*/shipments' => Http::response($this->damanCreated(), 201)]);

        $this->actingAs($this->user)
            ->post('http://localhost/orders-bulk/daman', ['ids' => [$order->id]])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame('DM-2026-0001', $order->daman_order_number);
        $this->assertSame('SNB-778899', $order->daman_tracking_number);
        $this->assertSame('سندباد إكسبريس', $order->daman_carrier_name);
        $this->assertSame(9001, $order->daman_shipment_id);
        $this->assertSame(Order::STATUS_SHIPPED, $order->status);
    }

    public function test_the_collected_amount_follows_the_stores_setting(): void
    {
        $this->link();
        $order = $this->order();

        Http::fake(['*/shipments' => Http::response($this->damanCreated(), 201)]);

        $this->actingAs($this->user)
            ->post('http://localhost/orders-bulk/daman', ['ids' => [$order->id]]);

        // Inclusive by default: the courier collects what the customer was
        // quoted, and Daman takes its shipping out of that.
        Http::assertSent(fn ($request) => data_get($request->data(), 'shipments.0.cod_amount') === 450.0);
    }

    public function test_when_shipping_is_added_on_top_only_the_goods_are_sent(): void
    {
        $this->link(['cod_includes_shipping' => false]);
        $order = $this->order();

        Http::fake(['*/shipments' => Http::response($this->damanCreated(), 201)]);

        $this->actingAs($this->user)
            ->post('http://localhost/orders-bulk/daman', ['ids' => [$order->id]]);

        Http::assertSent(fn ($request) => data_get($request->data(), 'shipments.0.cod_amount') === 400.0);
    }

    public function test_orders_that_are_not_confirmed_are_left_alone(): void
    {
        $this->link();
        $pending = $this->order(['status' => Order::STATUS_PENDING]);

        Http::fake();

        $this->actingAs($this->user)
            ->post('http://localhost/orders-bulk/daman', ['ids' => [$pending->id]])
            ->assertSessionHas('daman_result', fn ($result) => $result['sent'] === 0 && $result['skipped'] === 1);

        Http::assertNothingSent();
        $this->assertSame(Order::STATUS_PENDING, $pending->fresh()->status);
    }

    public function test_an_order_already_with_daman_is_not_sent_twice(): void
    {
        $this->link();
        $order = $this->order(['daman_shipment_id' => 9001]);

        Http::fake();

        $this->actingAs($this->user)
            ->post('http://localhost/orders-bulk/daman', ['ids' => [$order->id]]);

        Http::assertNothingSent();
    }

    public function test_a_rejected_order_keeps_its_status_and_records_why(): void
    {
        $this->link();
        $order = $this->order();

        Http::fake(['*/shipments' => Http::response([
            'data' => [
                'environment' => 'live', 'total' => 1, 'created' => 0, 'failed' => 1,
                'results' => [[
                    'status' => 'failed',
                    'index' => 0,
                    'errors' => ['governorate' => ["Could not resolve governorate from 'الإسكندرية'."]],
                ]],
            ],
        ], 422)]);

        $this->actingAs($this->user)
            ->post('http://localhost/orders-bulk/daman', ['ids' => [$order->id]]);

        $order->refresh();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertNull($order->daman_shipment_id);
        $this->assertStringContainsString('governorate', $order->daman_error);
    }

    public function test_an_order_with_no_governorate_never_reaches_daman(): void
    {
        $this->link();
        $order = $this->order(['governorate' => null]);

        Http::fake();

        $this->actingAs($this->user)
            ->post('http://localhost/orders-bulk/daman', ['ids' => [$order->id]])
            ->assertSessionHas('daman_result', fn ($result) => $result['failed'] === 1);

        Http::assertNothingSent();
    }

    public function test_a_merchant_cannot_ship_another_stores_order(): void
    {
        $this->link();
        $theirs = Order::factory()->for(Store::factory()->create())->create([
            'status' => Order::STATUS_CONFIRMED,
        ]);

        Http::fake();

        $this->actingAs($this->user)
            ->post('http://localhost/orders-bulk/daman', ['ids' => [$theirs->id]]);

        Http::assertNothingSent();
        $this->assertNull($theirs->fresh()->daman_shipment_id);
    }

    // ── Status updates ──────────────────────────────────────────────────────

    private function webhook(StoreDamanIntegration $link, array $payload): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        return $this->call(
            'POST',
            "/api/integrations/daman/webhook/{$link->webhook_token}",
            [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_DAMAN_SIGNATURE' => 'sha256=' . hash_hmac('sha256', $body, $link->webhook_secret),
            ],
            $body,
        );
    }

    public function test_a_signed_delivery_moves_the_order(): void
    {
        $link = $this->link();
        $order = $this->order(['status' => Order::STATUS_SHIPPED, 'daman_shipment_id' => 9001]);

        $this->webhook($link, [
            'event' => 'shipment.status_changed',
            'shipment_id' => 9001,
            'status' => 'delivered',
            'shipping_status' => 'delivered',
            'shipping_status_note' => 'تم التسليم',
            'tracking_number' => 'SNB-778899',
        ])->assertOk();

        $order->refresh();

        $this->assertSame(Order::STATUS_DELIVERED, $order->status);
        $this->assertSame('تم التسليم', $order->daman_status_note);
        $this->assertSame('SNB-778899', $order->daman_tracking_number);
    }

    /**
     * The carrier's view is the one that carries a refusal — Daman's own order
     * status never leaves 'in_delivery' for a parcel that came back.
     */
    public function test_a_refusal_marks_the_order_returned(): void
    {
        $link = $this->link();
        $order = $this->order(['status' => Order::STATUS_SHIPPED, 'daman_shipment_id' => 9001]);

        $this->webhook($link, [
            'shipment_id' => 9001,
            'status' => 'in_delivery',
            'shipping_status' => 'refused',
        ])->assertOk();

        $this->assertSame(Order::STATUS_RETURNED, $order->fresh()->status);
    }

    public function test_an_unsigned_update_is_refused(): void
    {
        $link = $this->link();
        $order = $this->order(['status' => Order::STATUS_SHIPPED, 'daman_shipment_id' => 9001]);

        $this->postJson("/api/integrations/daman/webhook/{$link->webhook_token}", [
            'shipment_id' => 9001,
            'shipping_status' => 'delivered',
        ])->assertStatus(401);

        $this->assertSame(Order::STATUS_SHIPPED, $order->fresh()->status);
    }

    public function test_a_delivered_order_is_not_walked_backwards(): void
    {
        $link = $this->link();
        $order = $this->order(['status' => Order::STATUS_DELIVERED, 'daman_shipment_id' => 9001]);

        $this->webhook($link, [
            'shipment_id' => 9001,
            'shipping_status' => 'out_for_delivery',
        ])->assertOk();

        $this->assertSame(Order::STATUS_DELIVERED, $order->fresh()->status);
    }

    public function test_an_unknown_token_is_not_told_it_is_unknown_twice(): void
    {
        $this->postJson('/api/integrations/daman/webhook/' . str_repeat('z', 40), [])
            ->assertStatus(404);
    }
}

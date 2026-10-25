<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreWhatsappIntegration;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminWhatsappTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function link(Store $store, string $driver, array $credentials = [], array $extra = []): void
    {
        $store->whatsappIntegration()->create([
            'driver' => $driver,
            'credentials' => $credentials,
            'is_active' => true,
            'webhook_token' => StoreWhatsappIntegration::newToken(),
            'verify_token' => StoreWhatsappIntegration::newToken(),
            ...$extra,
        ]);
    }

    /** @return array<string,mixed> */
    private function insights(): array
    {
        return $this->actingAs(Admin::factory()->create(), 'admin')
            ->get('http://localhost/admin/whatsapp?range=30d')
            ->assertOk()
            ->viewData('page')['props']['insights'];
    }

    public function test_it_counts_channels_and_lists_numbers_that_dropped(): void
    {
        $this->link(Store::factory()->create(), StoreWhatsappIntegration::DRIVER_PLATFORM);
        $this->link(Store::factory()->create(), StoreWhatsappIntegration::DRIVER_WAHA, ['status' => 'WORKING']);
        $dropped = Store::factory()->create(['name' => 'متجر واقع']);
        $this->link($dropped, StoreWhatsappIntegration::DRIVER_WAHA, ['status' => 'FAILED'], ['last_error' => 'الربط وقع.']);

        $insights = $this->insights();

        $this->assertSame(['platform' => 1, 'waha' => 2, 'active' => 3], $insights['channels']);
        $this->assertCount(1, $insights['problems']);
        $this->assertSame('متجر واقع', $insights['problems'][0]['store']['name']);
        $this->assertSame('FAILED', $insights['problems'][0]['status']);
    }

    public function test_it_counts_how_confirmations_did_per_store(): void
    {
        $store = Store::factory()->create(['name' => 'متجر شغّال']);
        $this->link($store, StoreWhatsappIntegration::DRIVER_WAHA, ['status' => 'WORKING']);

        $sent = ['whatsapp_sent_at' => now()->subDay()];
        Order::factory()->for($store)->create([...$sent, 'whatsapp_state' => 'confirmed']);
        Order::factory()->for($store)->create([...$sent, 'whatsapp_state' => 'confirmed']);
        Order::factory()->for($store)->create([...$sent, 'whatsapp_state' => 'cancelled']);
        Order::factory()->for($store)->create([...$sent, 'whatsapp_state' => 'sent']);
        Order::factory()->for($store)->create(['whatsapp_state' => 'failed']);
        // Outside the window.
        Order::factory()->for($store)->create(['whatsapp_sent_at' => now()->subDays(60), 'whatsapp_state' => 'confirmed']);

        $insights = $this->insights();

        $this->assertSame(['sent' => 4, 'confirmed' => 2, 'cancelled' => 1, 'awaiting' => 1, 'no_reply' => 0, 'failed' => 1], $insights['funnel']);
        $row = $insights['stores'][0];
        $this->assertSame('متجر شغّال', $row['store']);
        $this->assertSame('waha', $row['channel']);
        $this->assertSame(75, $row['answer_rate']);
    }

    /** Operators see counts and errors — never a customer's number or message. */
    public function test_no_customer_phone_or_message_reaches_the_page(): void
    {
        $store = Store::factory()->create();
        WhatsappMessage::create([
            'store_id' => $store->id, 'direction' => 'out', 'phone' => '201223344556',
            'body' => 'أهلاً سارة طلبك رقم 5', 'status' => 'failed', 'error' => 'سيرفر الواتساب رفض الطلب (400).',
        ]);

        // Read as decoded JSON: Inertia escapes Arabic in the HTML, so a
        // string search there would pass whatever the page contained.
        $props = json_encode(
            $this->actingAs(Admin::factory()->create(), 'admin')->get('http://localhost/admin/whatsapp')->assertOk()->viewData('page')['props'],
            JSON_UNESCAPED_UNICODE,
        );

        $this->assertStringContainsString('سيرفر الواتساب رفض الطلب', $props);
        $this->assertStringNotContainsString('201223344556', $props);
        $this->assertStringNotContainsString('أهلاً سارة', $props);
    }

    public function test_it_asks_meta_for_the_template_status_and_remembers_it(): void
    {
        config(['services.whatsapp.platform' => [
            'access_token' => 'tok', 'phone_number_id' => '111', 'app_secret' => 'sec',
            'business_account_id' => '999', 'template' => 'order_confirmation', 'template_language' => 'ar',
        ]]);
        Http::fake(['graph.facebook.com/*' => Http::response(['data' => [['name' => 'order_confirmation', 'status' => 'APPROVED']]])]);

        $this->assertSame('APPROVED', $this->insights()['meta']['template_status']);
        $this->insights();

        Http::assertSentCount(1);
    }
}

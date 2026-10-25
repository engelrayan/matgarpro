<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreWhatsappIntegration;
use App\Models\User;
use App\Services\Customers\ReputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminStoreDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = User::factory()->create(['email' => 'owner@example.com']);
        $this->store = Store::factory()->for($this->merchant)->create(['name' => 'متجر التجربة', 'slug' => 'tagroba']);
    }

    private function deleteAs(Admin $admin, string $confirm): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin, 'admin')
            ->delete("http://localhost/admin/stores/{$this->store->id}", ['confirm' => $confirm]);
    }

    public function test_a_super_admin_deletes_a_store_and_everything_in_it_for_good(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put("stores/{$this->store->id}/products/a.jpg", 'x');
        Storage::disk('public')->put("builder/{$this->store->id}/b.jpg", 'x');
        Storage::disk('public')->put('stores/999/products/other.jpg', 'x');

        $order = Order::factory()->for($this->store)->create();
        Product::factory()->for($this->store)->create();

        $this->deleteAs(Admin::factory()->super()->create(), 'tagroba')
            ->assertRedirect(route('admin.stores.index'))
            ->assertSessionHas('status');

        $this->assertNull(Store::withTrashed()->find($this->store->id), 'gone, not soft-deleted');
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('products', ['store_id' => $this->store->id]);

        Storage::disk('public')->assertMissing("stores/{$this->store->id}/products/a.jpg");
        Storage::disk('public')->assertMissing("builder/{$this->store->id}/b.jpg");
        Storage::disk('public')->assertExists('stores/999/products/other.jpg');

        // The merchant keeps their account.
        $this->assertNotNull($this->merchant->fresh());

        $log = AdminActivityLog::where('action', 'store.deleted')->sole();
        $this->assertStringContainsString('متجر التجربة', $log->summary);
        $this->assertStringContainsString('owner@example.com', $log->summary);
    }

    public function test_the_slug_has_to_be_typed_exactly(): void
    {
        $this->deleteAs(Admin::factory()->super()->create(), 'tagrob')
            ->assertSessionHasErrors('confirm');

        $this->assertNotNull($this->store->fresh());
    }

    public function test_staff_cannot_delete_a_store(): void
    {
        // Super-only screens answer 404 to staff, as the rest of the panel does.
        $this->deleteAs(Admin::factory()->create(), 'tagroba')->assertNotFound();

        $this->assertNotNull($this->store->fresh());
    }

    public function test_the_linked_whatsapp_number_is_unlinked_from_our_gateway(): void
    {
        config(['services.whatsapp.waha' => ['url' => 'http://waha.test', 'api_key' => 'k', 'webhook_secret' => 's', 'callback_url' => 'https://x.test', 'engine' => 'GOWS']]);
        $this->store->whatsappIntegration()->create([
            'driver' => StoreWhatsappIntegration::DRIVER_WAHA,
            'credentials' => ['status' => 'WORKING'],
            'webhook_token' => StoreWhatsappIntegration::newToken(),
            'verify_token' => StoreWhatsappIntegration::newToken(),
        ]);
        Http::fake(['waha.test/*' => Http::response([])]);

        $this->deleteAs(Admin::factory()->super()->create(), 'tagroba')->assertRedirect();

        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/api/sessions/store-' . $this->store->id));
    }

    /** A customer's record across the network no longer counts the deleted store's parcels. */
    public function test_the_network_delivery_record_is_counted_again_without_it(): void
    {
        $other = Store::factory()->create();
        $reputation = app(ReputationService::class);

        $mine = Order::factory()->for($this->store)->create(['customer_phone' => '01011112222', 'status' => Order::STATUS_RETURNED]);
        $theirs = Order::factory()->for($other)->create(['customer_phone' => '01011112222', 'status' => Order::STATUS_DELIVERED]);
        $reputation->record($mine);
        $reputation->record($theirs);

        $this->assertDatabaseHas('customer_reputations', ['phone' => '01011112222', 'refused' => 1, 'delivered' => 1]);

        $this->deleteAs(Admin::factory()->super()->create(), 'tagroba')->assertRedirect();

        $this->assertDatabaseHas('customer_reputations', ['phone' => '01011112222', 'refused' => 0, 'delivered' => 1]);
    }
}

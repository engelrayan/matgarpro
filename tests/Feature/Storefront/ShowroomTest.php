<?php

namespace Tests\Feature\Storefront;

use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreUsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Theme showrooms are real storefronts on the platform's own account. They
 * look exactly like a shop — which is the point, and also the risk.
 */
class ShowroomTest extends TestCase
{
    use RefreshDatabase;

    private function showroom(): Store
    {
        return Store::factory()->create([
            'slug' => 'demo-noir',
            'is_demo' => true,
            'status' => Store::STATUS_ACTIVE,
        ]);
    }

    public function test_the_seeder_builds_one_showroom_per_theme(): void
    {
        $this->artisan('demo:stores')->assertSuccessful();

        $this->assertSame(
            count((array) config('themes.themes')),
            Store::where('is_demo', true)->count(),
        );
    }

    public function test_running_the_seeder_twice_does_not_duplicate_showrooms(): void
    {
        $this->artisan('demo:stores');
        $before = Store::where('is_demo', true)->count();

        $this->artisan('demo:stores');

        $this->assertSame($before, Store::where('is_demo', true)->count());
    }

    public function test_each_showroom_carries_its_own_theme_and_catalogue(): void
    {
        $this->artisan('demo:stores');

        $noir = Store::where('slug', 'demo-noir')->firstOrFail();

        $this->assertSame('noir', $noir->settings['theme']);
        $this->assertGreaterThan(0, $noir->products()->count());
        $this->assertGreaterThan(0, $noir->categories()->count());
    }

    public function test_a_showroom_renders_with_its_ribbon(): void
    {
        $store = $this->showroom();

        $this->get('http://' . $store->platformHost())
            ->assertOk()
            ->assertSee('معرض للثيم');
    }

    /** A real store must never show the ribbon. */
    public function test_a_real_store_has_no_ribbon(): void
    {
        $store = Store::factory()->create(['is_demo' => false, 'status' => Store::STATUS_ACTIVE]);

        $this->get('http://' . $store->platformHost())
            ->assertOk()
            ->assertDontSee('معرض للثيم');
    }

    /**
     * The whole risk of a convincing demo: somebody fills in the form. It must
     * not become an order, and it must not bill the platform's own account.
     */
    public function test_a_showroom_refuses_orders(): void
    {
        $store = $this->showroom();
        $product = Product::factory()->for($store)->create();

        $this->post('http://' . $store->platformHost() . '/checkout', [
            'product_id' => $product->id,
            'quantity' => 1,
            'customer_name' => 'محمود',
            'customer_phone' => '01006262330',
            'address' => 'القاهرة',
        ])->assertSessionHasErrors();

        $this->assertSame(0, Order::count());
        $this->assertSame(0, StoreUsageEvent::count());
    }

    public function test_the_refusal_tells_the_visitor_what_to_do_instead(): void
    {
        $store = $this->showroom();
        $product = Product::factory()->for($store)->create();

        $this->post('http://' . $store->platformHost() . '/checkout', [
            'product_id' => $product->id,
            'quantity' => 1,
            'customer_name' => 'محمود',
            'customer_phone' => '01006262330',
            'address' => 'القاهرة',
        ])->assertSessionHasErrorsIn('default', ['checkout']);

        $this->assertStringContainsString(
            'اعمل متجرك',
            session('errors')->first('checkout'),
        );
    }

    public function test_the_theme_picker_links_every_theme_to_its_showroom(): void
    {
        $this->artisan('demo:stores');

        $user = User::factory()->create();
        Store::factory()->for($user)->create();

        $this->actingAs($user)
            ->get('http://localhost/settings/themes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Themes')
                ->where('themes.0.preview_url', fn ($url) => str_contains((string) $url, 'demo-')));
    }
}

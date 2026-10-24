<?php

namespace Tests\Feature\Settings;

use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\User;
use App\Services\Dns\DnsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDnsResolver;
use Tests\TestCase;

class DomainSettingsTest extends TestCase
{
    use RefreshDatabase;

    private FakeDnsResolver $dns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dns = new FakeDnsResolver();
        $this->app->instance(DnsResolver::class, $this->dns);
    }

    public function test_a_merchant_sees_their_domains_and_free_subdomain(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->for($user)->create(['slug' => 'mahmoud']);
        StoreDomain::factory()->for($store)->active()->primary()->create(['domain' => 'mahmoud.com']);

        $this->actingAs($user)
            ->get('http://localhost/settings/domains')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Domains')
                ->where('store.platform_host', 'mahmoud.mataager.test')
                ->where('domains.0.domain', 'mahmoud.com')
                ->where('domains.0.is_primary', true));
    }

    public function test_a_domain_pointing_at_us_waits_for_the_txt_proof_then_goes_live(): void
    {
        $user = User::factory()->create();
        Store::factory()->for($user)->create();

        $this->dns->pointA('mahmoud.com', '203.0.113.10');

        $this->actingAs($user)
            ->post('http://localhost/settings/domains', ['domain' => 'https://MAHMOUD.com/'])
            ->assertRedirect();

        $domain = StoreDomain::where('domain', 'mahmoud.com')->firstOrFail();
        $this->assertSame(StoreDomain::STATUS_PENDING, $domain->status);
        $this->assertStringContainsString('TXT', $domain->last_error);

        $this->dns->pointTxt('_matgarpro.mahmoud.com', $domain->verification_token);

        $this->actingAs($user)
            ->post("http://localhost/settings/domains/{$domain->id}/verify")
            ->assertRedirect();

        $this->assertSame(StoreDomain::STATUS_ACTIVE, $domain->fresh()->status);
    }

    public function test_a_bad_domain_comes_back_as_a_field_error(): void
    {
        $user = User::factory()->create();
        Store::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('http://localhost/settings/domains', ['domain' => 'not-a-domain'])
            ->assertSessionHasErrors('domain');
    }

    public function test_a_merchant_cannot_touch_another_merchants_domain(): void
    {
        $mine = User::factory()->create();
        Store::factory()->for($mine)->create();

        $theirs = Store::factory()->create();
        $domain = StoreDomain::factory()->for($theirs)->active()->create();

        $this->actingAs($mine)
            ->delete('http://localhost/settings/domains/' . $domain->id)
            ->assertForbidden();

        $this->assertDatabaseHas('store_domains', ['id' => $domain->id]);
    }

    public function test_registration_creates_a_store_on_the_default_plan(): void
    {
        $this->seed(\Database\Seeders\BillingPlanSeeder::class);

        $this->post('http://localhost/register', [
            'name' => 'محمود',
            'store_name' => 'متجر محمود',
            'email' => 'mahmoud@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $store = Store::firstOrFail();

        $this->assertSame('متجر محمود', $store->name);
        // The introductory price comes from the store's own clock, not from a
        // separate plan — the plan must still be attached, or a usage row would
        // record no plan and nothing would explain a historical charge.
        $this->assertSame(0.50, $store->pricePerOrder());
        $this->assertSame('intro', $store->priceSource());
        $this->assertNotNull($store->billing_plan_id, 'the plan must be attached explicitly');
        $this->assertSame(0.75, $store->basePricePerOrder());
    }

    /**
     * Str::slug() strips Arabic entirely, so every Arabic store name would
     * otherwise collide on an empty slug.
     */
    public function test_arabic_store_names_still_get_a_usable_subdomain(): void
    {
        $action = $this->app->make(\App\Actions\Stores\CreateStore::class);

        $first = $action->handle(User::factory()->create(), 'متجر محمود');
        $second = $action->handle(User::factory()->create(), 'متجر أحمد');

        $this->assertNotSame('', $first->slug);
        $this->assertNotSame($first->slug, $second->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $first->slug);
    }
}

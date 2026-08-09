<?php

namespace Tests\Feature\Storefront;

use App\Models\Store;
use App\Models\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_custom_domain_serves_its_own_store(): void
    {
        $store = Store::factory()->create(['name' => 'متجر محمود']);
        StoreDomain::factory()->for($store)->active()->primary()->create(['domain' => 'mahmoud.com']);

        $this->get('http://mahmoud.com/')
            ->assertOk()
            ->assertSee('متجر محمود', escape: false);
    }

    public function test_www_and_apex_reach_the_same_store(): void
    {
        $store = Store::factory()->create(['name' => 'متجر محمود']);
        StoreDomain::factory()->for($store)->active()->create(['domain' => 'mahmoud.com']);

        $this->get('http://www.mahmoud.com/')->assertOk()->assertSee('متجر محمود', escape: false);
    }

    public function test_the_free_platform_subdomain_always_works(): void
    {
        $store = Store::factory()->create(['slug' => 'mahmoud', 'name' => 'متجر محمود']);

        $this->get('http://mahmoud.mataager.test/')
            ->assertOk()
            ->assertSee('متجر محمود', escape: false);
    }

    public function test_an_unclaimed_hostname_is_not_served(): void
    {
        $this->get('http://someone-elses-domain.com/')->assertNotFound();
    }

    public function test_a_pending_domain_is_not_served_yet(): void
    {
        $store = Store::factory()->create();
        StoreDomain::factory()->for($store)->create([
            'domain' => 'mahmoud.com',
            'status' => StoreDomain::STATUS_PENDING,
        ]);

        $this->get('http://mahmoud.com/')->assertNotFound();
    }

    public function test_a_suspended_store_stops_serving_immediately(): void
    {
        $store = Store::factory()->suspended()->create();
        StoreDomain::factory()->for($store)->active()->create(['domain' => 'mahmoud.com']);

        $this->get('http://mahmoud.com/')->assertNotFound();
    }

    /**
     * The whole reason the dashboard is domain-constrained: without it the
     * dashboard's `/` is registered first and answers on every merchant domain.
     */
    public function test_the_dashboard_is_never_served_on_a_store_domain(): void
    {
        $store = Store::factory()->create(['name' => 'متجر محمود']);
        StoreDomain::factory()->for($store)->active()->create(['domain' => 'mahmoud.com']);

        $this->get('http://mahmoud.com/')
            ->assertOk()
            ->assertDontSee('matgarpro-dashboard');

        /*
         | And the storefront never answers on the dashboard host.
         |
         | Asserted on the canonical tag rather than the store's name: the
         | marketing page shows a mocked-up storefront, so any name a test
         | store happens to share with that mock would fail here for the wrong
         | reason. Only a real storefront render emits this link.
         */
        $this->get('http://localhost/')
            ->assertOk()
            ->assertDontSee('rel="canonical" href="https://mahmoud.com', escape: false);
    }

    public function test_the_canonical_host_falls_back_while_dns_is_pending(): void
    {
        $store = Store::factory()->create(['slug' => 'mahmoud']);
        StoreDomain::factory()->for($store)->primary()->create([
            'domain' => 'mahmoud.com',
            'status' => StoreDomain::STATUS_PENDING,
        ]);

        $this->assertSame('mahmoud.mataager.test', $store->canonicalHost());
    }
}

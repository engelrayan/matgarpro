<?php

namespace Tests\Feature\Api;

use App\Models\Store;
use App\Models\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The gate the edge proxy calls before asking a CA for a certificate. Getting
 * this wrong in the permissive direction means anyone can point DNS at us and
 * burn the account's issuance rate limit.
 */
class TlsCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_allows_a_domain_a_merchant_attached(): void
    {
        $store = Store::factory()->create();
        StoreDomain::factory()->for($store)->create(['domain' => 'mahmoud.com']);

        $this->get('/api/tls/check?domain=mahmoud.com')->assertOk();
    }

    public function test_it_allows_a_pending_domain_because_https_must_work_on_the_first_hit(): void
    {
        $store = Store::factory()->create();
        StoreDomain::factory()->for($store)->create([
            'domain' => 'mahmoud.com',
            'status' => StoreDomain::STATUS_PENDING,
        ]);

        $this->get('/api/tls/check?domain=mahmoud.com')->assertOk();
    }

    public function test_it_allows_a_platform_subdomain_of_a_real_store(): void
    {
        Store::factory()->create(['slug' => 'mahmoud']);

        $this->get('/api/tls/check?domain=mahmoud.mataager.test')->assertOk();
    }

    public function test_it_refuses_a_hostname_nobody_attached(): void
    {
        $this->get('/api/tls/check?domain=attacker.com')->assertNotFound();
    }

    public function test_it_refuses_a_platform_subdomain_with_no_store(): void
    {
        $this->get('/api/tls/check?domain=ghost.mataager.test')->assertNotFound();
    }

    public function test_it_rejects_a_call_with_no_domain(): void
    {
        $this->get('/api/tls/check')->assertStatus(400);
    }
}

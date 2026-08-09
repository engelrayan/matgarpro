<?php

namespace Tests\Feature\Storefront;

use App\Exceptions\DomainException;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Services\Dns\DnsResolver;
use App\Services\Storefront\StoreDomainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDnsResolver;
use Tests\TestCase;

class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    private FakeDnsResolver $dns;

    private StoreDomainService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dns = new FakeDnsResolver();
        $this->app->instance(DnsResolver::class, $this->dns);
        $this->service = $this->app->make(StoreDomainService::class);
    }

    public function test_it_normalizes_whatever_the_merchant_pastes(): void
    {
        $cases = [
            'MAHMOUD.COM' => 'mahmoud.com',
            'https://mahmoud.com/shop?utm=1' => 'mahmoud.com',
            'http://WWW.Mahmoud.com:8080/' => 'www.mahmoud.com',
            '  mahmoud.com.  ' => 'mahmoud.com',
            'shop.mahmoud.com/products' => 'shop.mahmoud.com',
        ];

        foreach ($cases as $input => $expected) {
            $this->assertSame($expected, $this->service->normalize($input), "failed on: {$input}");
        }
    }

    public function test_it_rejects_input_that_is_not_a_domain(): void
    {
        $store = Store::factory()->create();

        foreach (['mahmoud', '192.168.1.1', 'mahmoud.c0m', '-bad.com', ''] as $bad) {
            try {
                $this->service->attach($store, $bad);
                $this->fail("expected rejection for: {$bad}");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_it_refuses_a_domain_owned_by_another_store(): void
    {
        $other = Store::factory()->create();
        StoreDomain::factory()->for($other)->create(['domain' => 'mahmoud.com']);

        $this->expectException(DomainException::class);

        $this->service->attach(Store::factory()->create(), 'mahmoud.com');
    }

    public function test_re_adding_your_own_domain_is_a_no_op(): void
    {
        $store = Store::factory()->create();

        $first = $this->service->attach($store, 'mahmoud.com');
        $second = $this->service->attach($store, 'https://MAHMOUD.com/');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $store->domains()->count());
    }

    public function test_it_refuses_the_platform_domain(): void
    {
        $this->expectException(DomainException::class);

        $this->service->attach(Store::factory()->create(), 'shop.mataager.test');
    }

    public function test_the_first_domain_added_becomes_primary(): void
    {
        $store = Store::factory()->create();

        $first = $this->service->attach($store, 'mahmoud.com');
        $second = $this->service->attach($store, 'mahmoud.net');

        $this->assertTrue($first->is_primary);
        $this->assertFalse($second->is_primary);
        $this->assertSame(StoreDomain::STATUS_PENDING, $first->status);
    }

    public function test_it_activates_a_domain_whose_a_record_points_at_us(): void
    {
        $store = Store::factory()->create();
        $domain = $this->service->attach($store, 'mahmoud.com');

        $this->dns->pointA('mahmoud.com', '203.0.113.11');

        $domain = $this->service->verify($domain);

        $this->assertSame(StoreDomain::STATUS_ACTIVE, $domain->status);
        $this->assertNotNull($domain->verified_at);
        $this->assertNull($domain->last_error);
    }

    public function test_it_activates_a_subdomain_pointed_by_cname(): void
    {
        $store = Store::factory()->create();
        $domain = $this->service->attach($store, 'shop.mahmoud.com');

        $this->dns->pointCname('shop.mahmoud.com', 'connect.mataager.test');

        $this->assertSame(StoreDomain::STATUS_ACTIVE, $this->service->verify($domain)->status);
    }

    public function test_a_domain_pointing_elsewhere_stays_pending_with_a_readable_reason(): void
    {
        $store = Store::factory()->create();
        $domain = $this->service->attach($store, 'mahmoud.com');

        $this->dns->pointA('mahmoud.com', '198.51.100.7');

        $domain = $this->service->verify($domain);

        $this->assertSame(StoreDomain::STATUS_PENDING, $domain->status);
        $this->assertStringContainsString('198.51.100.7', $domain->last_error);
        $this->assertSame(1, $domain->check_attempts);
    }

    public function test_a_live_domain_survives_a_transient_dns_failure(): void
    {
        $store = Store::factory()->create();
        $domain = StoreDomain::factory()->for($store)->active()->create(['domain' => 'mahmoud.com']);

        // Resolver returns nothing at all — a SERVFAIL, not a real misconfig.
        $domain = $this->service->verify($domain);

        $this->assertSame(
            StoreDomain::STATUS_ACTIVE,
            $domain->status,
            'a serving domain must not be torn down by one bad lookup',
        );
    }

    public function test_a_never_working_domain_fails_after_the_give_up_window(): void
    {
        $store = Store::factory()->create();
        $domain = $this->service->attach($store, 'mahmoud.com');

        $domain->forceFill(['created_at' => now()->subHours(72)])->save();

        $this->assertSame(StoreDomain::STATUS_FAILED, $this->service->verify($domain)->status);
    }

    public function test_a_domain_cannot_be_made_primary_before_it_serves(): void
    {
        $store = Store::factory()->create();
        StoreDomain::factory()->for($store)->active()->primary()->create();
        $pending = $this->service->attach($store, 'mahmoud.com');

        $this->expectException(DomainException::class);

        $this->service->makePrimary($pending);
    }

    public function test_removing_the_primary_promotes_the_next_serving_domain(): void
    {
        $store = Store::factory()->create();
        $primary = StoreDomain::factory()->for($store)->active()->primary()->create();
        $backup = StoreDomain::factory()->for($store)->active()->create();

        $this->service->detach($primary);

        $this->assertTrue($backup->refresh()->is_primary);
    }

    public function test_apex_and_subdomain_get_different_dns_instructions(): void
    {
        $apex = StoreDomain::factory()->make(['domain' => 'mahmoud.com']);
        $sub = StoreDomain::factory()->make(['domain' => 'shop.mahmoud.com']);

        $this->assertTrue($apex->isApex());
        $this->assertSame('A', $apex->dnsInstructions()[0]['type']);

        $this->assertFalse($sub->isApex());
        $this->assertSame('CNAME', $sub->dnsInstructions()[0]['type']);
    }
}

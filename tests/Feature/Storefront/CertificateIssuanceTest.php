<?php

namespace Tests\Feature\Storefront;

use App\Jobs\IssueStoreDomainCertificate;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Services\Storefront\CertificateIssuer;
use App\Services\Storefront\StoreDomainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeDnsResolver;
use Tests\TestCase;

/**
 * Automatic certificates for merchant domains.
 *
 * Nothing here talks to Let's Encrypt — the tests are about *when* we ask and
 * when we refuse to. Asking at the wrong moment is the expensive mistake: the
 * CA rate-limits failures per hostname per hour, so a domain that gets asked
 * for on every re-check is a domain nobody can certify for the rest of the day.
 */
class CertificateIssuanceTest extends TestCase
{
    use RefreshDatabase;

    private FakeDnsResolver $dns;

    private StoreDomainService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dns = new FakeDnsResolver();
        $this->app->instance(\App\Services\Dns\DnsResolver::class, $this->dns);
        $this->service = $this->app->make(StoreDomainService::class);

        config(['storefront.ssl.enabled' => true]);
    }

    private function domain(array $attributes = []): StoreDomain
    {
        return StoreDomain::factory()->for(Store::factory())->create(array_merge([
            'domain' => 'mahmoud.com',
            'status' => StoreDomain::STATUS_PENDING,
        ], $attributes));
    }

    // ── When we ask ─────────────────────────────────────────────────────

    public function test_verifying_a_domain_for_the_first_time_queues_a_certificate(): void
    {
        Queue::fake();

        $domain = $this->domain();
        $this->dns->pointA('mahmoud.com', ...config('storefront.dns.a'));

        $this->service->verify($domain);

        $this->assertSame(StoreDomain::STATUS_ACTIVE, $domain->fresh()->status);
        Queue::assertPushed(IssueStoreDomainCertificate::class);
    }

    /**
     * The expensive mistake. A merchant pressing "check again" on a domain
     * that already works must not spend another certificate order.
     */
    public function test_rechecking_an_already_serving_domain_does_not_queue_another(): void
    {
        Queue::fake();

        $domain = $this->domain(['status' => StoreDomain::STATUS_ACTIVE]);
        $this->dns->pointA('mahmoud.com', ...config('storefront.dns.a'));

        $this->service->verify($domain);

        Queue::assertNothingPushed();
    }

    public function test_a_domain_that_never_verified_is_not_asked_for(): void
    {
        Queue::fake();

        $domain = $this->domain();
        // Points at somebody else entirely.
        $this->dns->pointA('mahmoud.com', '203.0.113.99');

        $this->service->verify($domain);

        $this->assertSame(StoreDomain::STATUS_PENDING, $domain->fresh()->status);
        Queue::assertNothingPushed();
    }

    // ── Eligibility ─────────────────────────────────────────────────────

    public function test_nothing_is_attempted_while_the_feature_is_switched_off(): void
    {
        config(['storefront.ssl.enabled' => false]);

        $domain = $this->domain(['status' => StoreDomain::STATUS_ACTIVE]);

        $this->assertFalse(app(CertificateIssuer::class)->eligible($domain));
    }

    public function test_a_domain_that_already_holds_a_certificate_is_left_alone(): void
    {
        $domain = $this->domain([
            'status' => StoreDomain::STATUS_ACTIVE,
            'ssl_status' => StoreDomain::SSL_ISSUED,
        ]);

        $this->assertFalse(app(CertificateIssuer::class)->eligible($domain));
    }

    public function test_a_domain_inside_its_backoff_window_is_left_alone(): void
    {
        $domain = $this->domain([
            'status' => StoreDomain::STATUS_ACTIVE,
            'ssl_status' => StoreDomain::SSL_FAILED,
            'ssl_retry_after' => now()->addHour(),
        ]);

        $this->assertFalse(app(CertificateIssuer::class)->eligible($domain));

        // …and is picked up again once the window has passed.
        $domain->forceFill(['ssl_retry_after' => now()->subMinute()])->save();

        $this->assertTrue(app(CertificateIssuer::class)->eligible($domain->fresh()));
    }

    public function test_a_domain_gives_up_after_the_attempt_ceiling(): void
    {
        $domain = $this->domain([
            'status' => StoreDomain::STATUS_ACTIVE,
            'ssl_status' => StoreDomain::SSL_FAILED,
            'ssl_attempts' => config('storefront.ssl.max_attempts'),
        ]);

        $this->assertFalse(app(CertificateIssuer::class)->eligible($domain));
    }

    // ── What the merchant is told ───────────────────────────────────────

    public function test_serving_and_secure_are_reported_as_two_separate_states(): void
    {
        $store = Store::factory()->create();
        $user = $store->user;

        StoreDomain::factory()->for($store)->create([
            'domain' => 'mahmoud.com',
            'status' => StoreDomain::STATUS_ACTIVE,
            'ssl_status' => StoreDomain::SSL_PENDING,
        ]);

        $this->actingAs($user)->get('/settings/domains')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Serving…
                ->where('domains.0.status', StoreDomain::STATUS_ACTIVE)
                // …but not yet secure. One badge could not say both.
                ->where('domains.0.is_secure', false)
                ->where('domains.0.ssl_status', StoreDomain::SSL_PENDING));
    }

    public function test_a_certified_domain_reports_itself_as_secure(): void
    {
        $store = Store::factory()->create();

        StoreDomain::factory()->for($store)->create([
            'domain' => 'mahmoud.com',
            'status' => StoreDomain::STATUS_ACTIVE,
            'ssl_status' => StoreDomain::SSL_ISSUED,
        ]);

        $this->actingAs($store->user)->get('/settings/domains')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('domains.0.is_secure', true));
    }
}

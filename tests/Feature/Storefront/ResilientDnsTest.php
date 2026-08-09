<?php

namespace Tests\Feature\Storefront;

use App\Services\Dns\DnsResolver;
use App\Services\Dns\ResilientDnsResolver;
use App\Services\Dns\SystemDnsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The second opinion, for when the server's own resolver is the broken part.
 *
 * This exists because it happened twice: a domain every public resolver
 * answered correctly came back empty from `dns_get_record`, and a merchant sat
 * looking at "الـ DNS لسه بينتشر" about DNS that had propagated hours earlier.
 */
class ResilientDnsTest extends TestCase
{
    use RefreshDatabase;

    private function resolverWithSystemReturning(array $a = [], ?string $cname = null): ResilientDnsResolver
    {
        $system = new class($a, $cname) extends SystemDnsResolver
        {
            public function __construct(private array $a, private ?string $cname) {}

            public function aRecords(string $domain): array
            {
                return $this->a;
            }

            public function cname(string $domain): ?string
            {
                return $this->cname;
            }

            public function txtRecords(string $domain): array
            {
                return [];
            }
        };

        return new ResilientDnsResolver($system);
    }

    public function test_the_host_resolver_is_believed_when_it_answers(): void
    {
        Http::fake();

        $records = $this->resolverWithSystemReturning(['203.0.113.5'])->aRecords('mahmoud.com');

        $this->assertSame(['203.0.113.5'], $records);
        // Faster, offline-safe, and respects any split-horizon the operator
        // set up — so it is asked first and not second-guessed.
        Http::assertNothingSent();
    }

    public function test_an_empty_host_answer_is_checked_over_https(): void
    {
        Http::fake([
            '*' => Http::response(['Status' => 0, 'Answer' => [
                ['name' => 'mahmoud.com', 'type' => 1, 'data' => '144.91.81.107'],
            ]]),
        ]);

        $records = $this->resolverWithSystemReturning([])->aRecords('mahmoud.com');

        $this->assertSame(['144.91.81.107'], $records);
    }

    /**
     * A CNAME chain comes back as several answers of different types. Only the
     * type actually asked for is an answer to the question.
     */
    public function test_answers_of_the_wrong_type_are_ignored(): void
    {
        Http::fake([
            '*' => Http::response(['Status' => 0, 'Answer' => [
                ['name' => 'www.mahmoud.com', 'type' => 5, 'data' => 'connect.matgarpro.com.'],
                ['name' => 'connect.matgarpro.com', 'type' => 1, 'data' => '144.91.81.107'],
            ]]),
        ]);

        $resolver = $this->resolverWithSystemReturning([], null);

        $this->assertSame('connect.matgarpro.com', $resolver->cname('www.mahmoud.com'));
        $this->assertSame(['144.91.81.107'], $resolver->aRecords('www.mahmoud.com'));
    }

    public function test_a_domain_that_genuinely_does_not_exist_stays_empty(): void
    {
        Http::fake(['*' => Http::response(['Status' => 3])]);

        $this->assertSame([], $this->resolverWithSystemReturning([])->aRecords('nope.invalid'));
    }

    /**
     * A resolver that throws turns "we could not check right now" into a 500
     * on a merchant's settings page — a worse answer than "not pointed at us".
     */
    public function test_the_fallback_failing_is_never_an_exception(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->assertSame([], $this->resolverWithSystemReturning([])->aRecords('mahmoud.com'));
    }

    public function test_it_can_be_switched_off_entirely(): void
    {
        config(['storefront.dns.doh' => false]);
        Http::fake();

        $this->assertSame([], $this->resolverWithSystemReturning([])->aRecords('mahmoud.com'));
        Http::assertNothingSent();
    }

    public function test_the_application_resolves_the_resilient_one_by_default(): void
    {
        $this->assertInstanceOf(ResilientDnsResolver::class, app(DnsResolver::class));
    }
}

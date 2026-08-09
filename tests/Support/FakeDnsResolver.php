<?php

namespace Tests\Support;

use App\Services\Dns\DnsResolver;

/**
 * An in-memory zone file. Lets domain tests assert on real behaviour without
 * touching the network, which would be slow, flaky, and different on every
 * developer's machine.
 */
class FakeDnsResolver implements DnsResolver
{
    /** @var array<string, array<int, string>> */
    private array $a = [];

    /** @var array<string, string> */
    private array $cname = [];

    /** @var array<string, array<int, string>> */
    private array $txt = [];

    public function pointA(string $domain, string ...$ips): self
    {
        $this->a[strtolower($domain)] = $ips;

        return $this;
    }

    public function pointCname(string $domain, string $target): self
    {
        $this->cname[strtolower($domain)] = strtolower($target);

        return $this;
    }

    public function pointTxt(string $domain, string ...$values): self
    {
        $this->txt[strtolower($domain)] = $values;

        return $this;
    }

    public function aRecords(string $domain): array
    {
        return $this->a[strtolower($domain)] ?? [];
    }

    public function cname(string $domain): ?string
    {
        return $this->cname[strtolower($domain)] ?? null;
    }

    public function txtRecords(string $domain): array
    {
        return $this->txt[strtolower($domain)] ?? [];
    }
}

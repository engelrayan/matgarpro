<?php

namespace App\Services\Dns;

interface DnsResolver
{
    /**
     * Every IPv4 address the hostname resolves to. A CNAME chain is followed,
     * so a domain CNAME'd to us still reports our edge IPs here.
     *
     * @return array<int, string>
     */
    public function aRecords(string $domain): array;

    /**
     * The CNAME target, without the trailing dot, or null when the hostname
     * holds no CNAME (which is always the case at a zone apex).
     */
    public function cname(string $domain): ?string;

    /**
     * TXT record values for the hostname, used for the ownership proof a
     * merchant can add before repointing a domain that is still live elsewhere.
     *
     * @return array<int, string>
     */
    public function txtRecords(string $domain): array;
}

<?php

namespace App\Services\Dns;

/**
 * DNS lookups through the host resolver.
 *
 * Every method swallows resolver failures and returns "nothing found" rather
 * than throwing: a merchant whose nameservers are momentarily down should see
 * "not pointed at us yet", not a 500.
 */
class SystemDnsResolver implements DnsResolver
{
    public function aRecords(string $domain): array
    {
        $records = $this->query($domain, DNS_A);

        return array_values(array_filter(array_map(
            fn (array $r) => $r['ip'] ?? null,
            $records,
        )));
    }

    public function cname(string $domain): ?string
    {
        $records = $this->query($domain, DNS_CNAME);

        foreach ($records as $record) {
            if (! empty($record['target'])) {
                return rtrim(strtolower($record['target']), '.');
            }
        }

        return null;
    }

    public function txtRecords(string $domain): array
    {
        $records = $this->query($domain, DNS_TXT);

        $values = [];
        foreach ($records as $record) {
            // PHP splits long TXT strings into `entries`; `txt` holds the join.
            if (! empty($record['txt'])) {
                $values[] = $record['txt'];
            }
        }

        return $values;
    }

    /** @return array<int, array<string, mixed>> */
    private function query(string $domain, int $type): array
    {
        // dns_get_record emits a warning and returns false on SERVFAIL/NXDOMAIN.
        $records = @dns_get_record($domain, $type);

        return is_array($records) ? $records : [];
    }
}

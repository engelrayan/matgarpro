<?php

namespace App\Services\Dns;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The host resolver, with a way out when it is the thing that is broken.
 *
 * Custom domains — the whole feature — hang off one call to `dns_get_record`.
 * Twice now that call has returned nothing for a domain that every public
 * resolver answers correctly, because the server's own `systemd-resolved` had
 * a bad cache entry or was pointed at an uplink that could not answer for that
 * TLD. Both times a merchant sat looking at "الـ DNS لسه بينتشر" about a domain
 * that had been correct for hours, and both times the platform had no idea
 * anything was wrong.
 *
 * So an empty answer from the host is no longer taken as fact. It is checked
 * against Cloudflare over HTTPS — port 443, no resolver involved, nothing for
 * a misconfigured box to get in the way of.
 *
 * The order matters: the host resolver is asked first and believed when it
 * answers. It is faster, it respects whatever split-horizon the operator has
 * set up, and it works offline. DoH is the second opinion, not the default.
 */
class ResilientDnsResolver implements DnsResolver
{
    /** DNS record type numbers, as they appear in a DoH JSON answer. */
    private const TYPE_A = 1;

    private const TYPE_CNAME = 5;

    private const TYPE_TXT = 16;

    public function __construct(private readonly SystemDnsResolver $system) {}

    public function aRecords(string $domain): array
    {
        $records = $this->system->aRecords($domain);

        if ($records !== []) {
            return $records;
        }

        return $this->overHttps($domain, 'A', self::TYPE_A);
    }

    public function cname(string $domain): ?string
    {
        $cname = $this->system->cname($domain);

        if ($cname !== null) {
            return $cname;
        }

        $answers = $this->overHttps($domain, 'CNAME', self::TYPE_CNAME);

        return $answers[0] ?? null;
    }

    public function txtRecords(string $domain): array
    {
        $records = $this->system->txtRecords($domain);

        if ($records !== []) {
            return $records;
        }

        return $this->overHttps($domain, 'TXT', self::TYPE_TXT);
    }

    /**
     * Ask Cloudflare directly, over HTTPS.
     *
     * Returns an empty array on any failure — a resolver that throws would
     * turn "we could not check right now" into a 500 on a merchant's settings
     * page, which is a worse answer than "not pointed at us yet".
     *
     * @return array<int,string>
     */
    private function overHttps(string $domain, string $type, int $expected): array
    {
        if (! config('storefront.dns.doh')) {
            return [];
        }

        try {
            $response = Http::withHeaders(['Accept' => 'application/dns-json'])
                // Short: this runs inside a merchant pressing a button. A
                // resolver that takes ten seconds to fail has already lost.
                ->timeout(4)
                ->get(config('storefront.dns.doh_endpoint'), ['name' => $domain, 'type' => $type]);

            if (! $response->successful()) {
                return [];
            }

            $answers = collect($response->json('Answer') ?? [])
                // A CNAME chain comes back as several answers of different
                // types; only the type actually asked for is an answer to the
                // question.
                ->where('type', $expected)
                ->pluck('data')
                ->map(fn (string $value) => trim(trim($value, '"'), '.'))
                ->filter()
                ->values()
                ->all();

            if ($answers !== []) {
                /*
                 | Worth a line in the log every time. It means the host
                 | resolver is lying, and the only reason anybody would find
                 | that out is if something says so — the feature keeps working
                 | either way, which is exactly how a broken resolver stayed
                 | invisible for two days.
                 */
                Log::notice('host resolver returned nothing; answered over DoH instead', [
                    'domain' => $domain,
                    'type' => $type,
                ]);
            }

            return $answers;
        } catch (\Throwable $e) {
            Log::warning('DoH lookup failed', ['domain' => $domain, 'error' => $e->getMessage()]);

            return [];
        }
    }
}

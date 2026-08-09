<?php

namespace App\Services\Storefront;

use App\Exceptions\DomainException;
use App\Jobs\IssueStoreDomainCertificate;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Services\Dns\DnsResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Attaching, verifying and retiring the custom domains a merchant points at
 * their store.
 *
 * The trust model is deliberately simple: control of a domain's DNS is proof of
 * ownership. We never issue anything for a hostname that has not already been
 * pointed at us, so a merchant typing someone else's domain into the form
 * achieves nothing.
 */
class StoreDomainService
{
    public function __construct(private readonly DnsResolver $dns) {}

    /**
     * Reduce anything a merchant might paste — a full URL, a trailing dot, an
     * Arabic IDN, "WWW.Mahmoud.COM/shop?x=1" — to a bare, comparable hostname.
     */
    public function normalize(string $input): string
    {
        $value = trim($input);

        // Strip a scheme and everything from the first slash onwards.
        $value = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $value) ?? $value;
        $value = Str::before($value, '/');
        $value = Str::before($value, '?');

        // Drop credentials and an explicit port.
        $value = Str::afterLast($value, '@');
        $value = preg_replace('/:\d+$/', '', $value) ?? $value;

        $value = rtrim(strtolower(trim($value)), '.');

        // Arabic and other non-ASCII domains must be stored as punycode so the
        // Host header we receive at request time compares equal.
        if ($value !== '' && ! preg_match('/^[\x20-\x7f]+$/', $value) && function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($value, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($ascii) && $ascii !== '') {
                $value = $ascii;
            }
        }

        return $value;
    }

    /**
     * @throws DomainException
     */
    public function attach(Store $store, string $input): StoreDomain
    {
        $domain = $this->normalize($input);

        $this->assertValidFormat($domain);
        $this->assertNotReserved($domain);

        return DB::transaction(function () use ($store, $domain) {
            // Re-check inside the transaction; the unique index is the real
            // guard, this just turns a race into a readable message.
            $existing = StoreDomain::where('domain', $domain)->lockForUpdate()->first();

            if ($existing) {
                if ($existing->store_id === $store->id) {
                    return $existing;
                }

                throw DomainException::alreadyTaken($domain);
            }

            $isFirst = ! $store->domains()->exists();

            return $store->domains()->create([
                'domain' => $domain,
                // The first domain a merchant adds becomes primary the moment it
                // starts serving; until then the platform sub-domain is canonical.
                'is_primary' => $isFirst,
                'status' => StoreDomain::STATUS_PENDING,
                'verification_token' => StoreDomain::mintToken(),
            ]);
        });
    }

    /**
     * Look the domain up in DNS and move it between pending/active/failed.
     *
     * Returns the refreshed record. Safe to call as often as you like — it
     * writes only what changed and never throws on a resolver failure.
     */
    public function verify(StoreDomain $domain): StoreDomain
    {
        $result = $this->check($domain->domain);

        $domain->last_checked_at = now();
        $domain->check_attempts = $domain->check_attempts + 1;

        if ($result['points_at_us']) {
            $domain->status = StoreDomain::STATUS_ACTIVE;
            $domain->last_error = null;
            $domain->verified_at ??= now();
        } else {
            $giveUpAfter = (int) config('storefront.verification.give_up_after_hours');
            $expired = $domain->created_at?->addHours($giveUpAfter)->isPast() ?? false;

            // Only a domain that was never right is failed. One that is already
            // serving stays active through a transient resolver hiccup — flipping
            // a live store to "broken" because of one bad lookup is worse than
            // being slow to notice a real break.
            if ($domain->status !== StoreDomain::STATUS_ACTIVE && $expired) {
                $domain->status = StoreDomain::STATUS_FAILED;
            }

            $domain->last_error = $result['reason'];
        }

        $wasServing = $domain->getOriginal('status') === StoreDomain::STATUS_ACTIVE;

        $domain->save();

        /*
         | The moment a hostname starts pointing at us is the only moment it
         | can answer an ACME challenge — so that is when the certificate is
         | asked for, not on a nightly sweep the merchant would sit through.
         |
         | Only on the transition into `active`: re-checking an already-serving
         | domain must not queue a second order, because Let's Encrypt counts
         | those against a weekly limit the merchant never sees.
         */
        if (! $wasServing && $domain->status === StoreDomain::STATUS_ACTIVE) {
            IssueStoreDomainCertificate::dispatch($domain->id);
        }

        return $domain->refresh();
    }

    /**
     * Does this hostname currently resolve to our infrastructure?
     *
     * @return array{points_at_us: bool, reason: ?string, a: array<int,string>, cname: ?string}
     */
    public function check(string $domain): array
    {
        $expectedIps = collect(config('storefront.dns.a'))->filter()->all();
        $expectedCname = strtolower((string) config('storefront.dns.cname'));

        $cname = $this->dns->cname($domain);
        $aRecords = $this->dns->aRecords($domain);

        if ($cname !== null && $expectedCname !== '' && $cname === $expectedCname) {
            return ['points_at_us' => true, 'reason' => null, 'a' => $aRecords, 'cname' => $cname];
        }

        // A CNAME to us resolves through to our IPs too, so the A check covers
        // both shapes and is the one that actually decides.
        $matched = array_intersect($aRecords, $expectedIps);

        if ($matched !== []) {
            return ['points_at_us' => true, 'reason' => null, 'a' => $aRecords, 'cname' => $cname];
        }

        $reason = match (true) {
            $aRecords === [] && $cname === null => 'الدومين لسه مش بيرجّع أي سجل — يمكن الـ DNS لسه بينتشر (ممكن ياخد لحد ٢٤ ساعة).',
            default => 'الدومين بيشاور على مكان تاني (' . (implode(', ', $aRecords) ?: $cname) . '). راجع سجلات الـ DNS.',
        };

        return ['points_at_us' => false, 'reason' => $reason, 'a' => $aRecords, 'cname' => $cname];
    }

    /** Has the merchant added our TXT proof? Optional path for live domains. */
    public function hasOwnershipToken(StoreDomain $domain): bool
    {
        $values = $this->dns->txtRecords('_matgarpro.' . $domain->domain);

        return in_array($domain->verification_token, $values, true)
            || in_array($domain->verification_token, $this->dns->txtRecords($domain->domain), true);
    }

    /**
     * @throws DomainException
     */
    public function makePrimary(StoreDomain $domain): StoreDomain
    {
        if (! $domain->isServing()) {
            throw DomainException::notServing();
        }

        DB::transaction(function () use ($domain) {
            StoreDomain::where('store_id', $domain->store_id)
                ->where('id', '!=', $domain->id)
                ->update(['is_primary' => false]);

            $domain->forceFill(['is_primary' => true])->save();
        });

        return $domain->refresh();
    }

    /**
     * @throws DomainException
     */
    public function detach(StoreDomain $domain): void
    {
        // Before the row goes: the generated nginx vhost is named after this
        // hostname, and a config file pointing at a store that no longer owns
        // the domain is how one merchant ends up serving another's shop.
        app(CertificateIssuer::class)->forget($domain->domain);

        DB::transaction(function () use ($domain) {
            $domain->delete();

            // Never leave a store without a primary: promote the next serving
            // domain, otherwise canonical URLs silently fall back to the
            // platform sub-domain with no indication why.
            if ($domain->is_primary) {
                $next = StoreDomain::where('store_id', $domain->store_id)
                    ->where('status', StoreDomain::STATUS_ACTIVE)
                    ->orderBy('id')
                    ->first();

                $next?->forceFill(['is_primary' => true])->save();
            }
        });
    }

    /**
     * @throws DomainException
     */
    private function assertValidFormat(string $domain): void
    {
        if ($domain === '' || ! str_contains($domain, '.')) {
            throw DomainException::invalidFormat($domain);
        }

        // Reject bare IPs — a certificate can never be issued for one.
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            throw DomainException::invalidFormat($domain);
        }

        if (strlen($domain) > 253) {
            throw DomainException::invalidFormat($domain);
        }

        $labelPattern = '/^(?!-)[a-z0-9-]{1,63}(?<!-)$/';

        foreach (explode('.', $domain) as $label) {
            if (! preg_match($labelPattern, $label)) {
                throw DomainException::invalidFormat($domain);
            }
        }

        // The public suffix must be alphabetic — catches "mahmoud.c0m" typos.
        if (! preg_match('/^[a-z]{2,}$/', Str::afterLast($domain, '.'))) {
            throw DomainException::invalidFormat($domain);
        }
    }

    /**
     * @throws DomainException
     */
    private function assertNotReserved(string $domain): void
    {
        $platform = strtolower((string) config('storefront.domain'));

        if ($domain === $platform || str_ends_with($domain, '.' . $platform)) {
            throw DomainException::platformDomain();
        }

        foreach ((array) config('storefront.blocked_domains') as $blocked) {
            $blocked = strtolower((string) $blocked);

            if ($domain === $blocked || str_ends_with($domain, '.' . $blocked)) {
                throw DomainException::blocked($domain);
            }
        }
    }
}

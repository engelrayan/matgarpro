<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Services\Storefront\StoreResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * On-demand TLS gate for the edge proxy (Caddy `on_demand_tls.ask`).
 *
 * Caddy calls this with `?domain=` before requesting a certificate for a
 * hostname it has never seen. A 2xx means "go ahead"; anything else means the
 * handshake is refused and no certificate is requested.
 *
 * This endpoint is the only thing standing between us and an attacker pointing
 * a thousand hostnames at our IP to exhaust the CA rate limit, so it answers
 * yes for exactly two cases: a domain a merchant attached, and our own
 * sub-domains.
 */
class TlsCheckController extends Controller
{
    public function __invoke(Request $request, StoreResolver $resolver): Response
    {
        $host = $resolver->normalizeHost((string) $request->query('domain', ''));

        if ($host === '') {
            return response('missing domain', 400);
        }

        if ($this->isPlatformStoreHost($host) || $this->isAttachedDomain($host)) {
            return response('ok', 200);
        }

        return response('unknown host', 404);
    }

    /** {slug}.matgarpro.com for a store that exists. */
    private function isPlatformStoreHost(string $host): bool
    {
        $platform = strtolower((string) config('storefront.domain'));

        if ($platform === '' || ! str_ends_with($host, '.' . $platform)) {
            return false;
        }

        $slug = substr($host, 0, -1 * (strlen($platform) + 1));

        if ($slug === '' || str_contains($slug, '.')) {
            return false;
        }

        return Store::where('slug', $slug)->exists();
    }

    /**
     * A hostname a merchant attached. `pending` counts: the certificate has to
     * exist before the first HTTPS request can succeed, and DNS already
     * pointing here is itself the proof of control.
     */
    private function isAttachedDomain(string $host): bool
    {
        return StoreDomain::where('domain', $host)
            ->whereIn('status', [StoreDomain::STATUS_PENDING, StoreDomain::STATUS_ACTIVE])
            ->exists();
    }
}

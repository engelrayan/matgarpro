<?php

namespace App\Services\Storefront;

use App\Models\Store;
use App\Models\StoreDomain;
use Illuminate\Support\Facades\Cache;

/**
 * Turns an inbound Host header into the store that owns it.
 *
 * This runs on every storefront request, including the ones from ad traffic, so
 * it is cached hard and invalidated explicitly whenever a domain or slug moves.
 */
class StoreResolver
{
    private const CACHE_TTL = 300;

    public function __construct(private readonly StoreDomainService $domains) {}

    public function resolve(string $host): ?Store
    {
        $host = $this->normalizeHost($host);

        if ($host === '') {
            return null;
        }

        $storeId = Cache::remember(
            $this->cacheKey($host),
            self::CACHE_TTL,
            fn () => $this->lookup($host),
        );

        if ($storeId === null) {
            return null;
        }

        $store = Store::find($storeId);

        // A suspended or deleted store must stop serving immediately, so the
        // status is read live rather than baked into the cached mapping.
        return $store?->isActive() ? $store : null;
    }

    /** Drop the port and a leading `www.` so both spellings hit one store. */
    public function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = rtrim($host, '.');

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    public function forget(string $host): void
    {
        Cache::forget($this->cacheKey($this->normalizeHost($host)));
    }

    private function cacheKey(string $host): string
    {
        return 'storefront:host:' . $host;
    }

    /** @return int|null the store id, or null when nothing owns this host */
    private function lookup(string $host): ?int
    {
        $platform = strtolower((string) config('storefront.domain'));

        // Platform sub-domain: {slug}.matgarpro.com — always works, even while a
        // merchant's own DNS is broken.
        if ($platform !== '' && str_ends_with($host, '.' . $platform)) {
            $slug = substr($host, 0, -1 * (strlen($platform) + 1));

            // Only a single label; deeper names are not stores.
            if ($slug === '' || str_contains($slug, '.')) {
                return null;
            }

            return Store::where('slug', $slug)->value('id');
        }

        return StoreDomain::where('domain', $host)
            ->where('status', StoreDomain::STATUS_ACTIVE)
            ->value('store_id');
    }
}

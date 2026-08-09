<?php

namespace App\Services\Builder;

use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A short-lived pass that lets the builder's iframe see the draft.
 *
 * A cached random token rather than a Laravel signed URL, because the two ends
 * are on different hostnames: the builder runs on the dashboard domain and the
 * preview loads `{slug}.matgarpro.com` or the merchant's own domain. Signed
 * URLs are bound to the app URL and would fail the signature check the moment
 * they arrive somewhere else. A session cookie has the same problem, from the
 * other direction.
 *
 * The token carries the store id, and the middleware checks it against the
 * store the hostname actually resolved to — so a token minted for one shop
 * cannot be pasted onto another shop's domain to read its unpublished work.
 */
class PreviewToken
{
    /** Long enough to lay out a page, short enough that a leaked link is stale. */
    private const TTL_MINUTES = 60;

    public const QUERY_KEY = '_preview';

    public function issue(Store $store): string
    {
        $token = Str::random(40);

        Cache::put($this->cacheKey($token), $store->id, now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    /** The store this token was issued for, if it is still valid. */
    public function storeId(string $token): ?int
    {
        return Cache::get($this->cacheKey($token));
    }

    /** A browsable preview URL for one page of one store. */
    public function urlFor(Store $store, string $page, string $token): string
    {
        $path = match ($page) {
            // The header and the footer are on every page, so they are
            // previewed on the home page — there is nowhere else to stand.
            'home', 'header', 'footer' => '/',
            'product' => $this->firstProductPath($store),
            'category' => $this->firstCategoryPath($store),
            default => '/',
        };

        return $store->canonicalUrl() . $path
            . (str_contains($path, '?') ? '&' : '?') . self::QUERY_KEY . '=' . $token;
    }

    /**
     * Laying out the product page needs a product on screen. An empty shop
     * gets the home page instead of a 404 that looks like the builder broke.
     */
    private function firstProductPath(Store $store): string
    {
        $slug = $store->products()->where('status', 'active')->orderBy('sort_order')->value('slug');

        return $slug ? '/p/' . $slug : '/';
    }

    private function firstCategoryPath(Store $store): string
    {
        $slug = $store->categories()->where('is_active', true)->orderBy('sort_order')->value('slug');

        return $slug ? '/c/' . $slug : '/';
    }

    private function cacheKey(string $token): string
    {
        return 'builder-preview:' . $token;
    }
}

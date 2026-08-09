<?php

namespace App\Http\Middleware;

use App\Services\Builder\PageRenderer;
use App\Services\Builder\PreviewToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns on draft rendering for a storefront request carrying a valid preview
 * token — and only for that request.
 *
 * Runs after `ResolveStore`, so the hostname has already decided which shop
 * this is. The token's store id must match it: without that check, a merchant
 * could mint a token for their own shop and load a competitor's domain with it
 * to read work that has not been published.
 *
 * Preview responses are also marked no-store and noindex. A draft that ends up
 * in a CDN or in Google is a draft that got published by accident.
 */
class BuilderPreview
{
    public function __construct(private readonly PreviewToken $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->query(PreviewToken::QUERY_KEY);
        // `ResolveStore` puts the tenant on the request attributes, not on the
        // route — the hostname is what identifies a shop here, and no
        // storefront URL carries a store id to bind.
        $store = $request->attributes->get('store');

        if ($token !== '' && $store && $this->tokens->storeId($token) === $store->id) {
            $request->attributes->set(PageRenderer::PREVIEW_ATTRIBUTE, true);

            $response = $next($request);
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

            return $response;
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Services\Storefront\StoreResolver;
use App\Services\Storefront\ThemeResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the store that owns the request's Host into the container, so every
 * storefront controller can type-hint `Store` and get the right tenant without
 * ever reading the hostname itself.
 *
 * A hostname nobody owns 404s here rather than deeper in the stack — it means
 * someone pointed DNS at us for a domain that was never attached.
 */
class ResolveStore
{
    public function __construct(
        private readonly StoreResolver $resolver,
        private readonly ThemeResolver $themes,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $store = $this->resolver->resolve($request->getHost());

        abort_if($store === null, 404);

        app()->instance(Store::class, $store);
        $request->attributes->set('store', $store);

        /*
         | Shared from here rather than passed by each controller.
         |
         | All three are used by the layout, so a page whose controller forgot
         | one would render without its branding or silently stop tracking —
         | neither of which looks like an error to anybody.
         */
        view()->share('store', $store);
        view()->share('pixels', $store->activePixels());
        view()->share('theme', $this->themes->forStore($store));

        return $next($request);
    }
}

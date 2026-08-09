<?php

namespace App\Providers;

use App\Models\Store;
use App\Services\Builder\PageRenderer;
use App\Services\Dns\DnsResolver;
use App\Services\Dns\ResilientDnsResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
        | Bound as an interface so tests can swap in a fake zone file instead of
        | making real DNS queries, which are slow and flaky in CI.
        |
        | The resilient one wraps the system resolver and falls back to DNS over
        | HTTPS when it answers with nothing — see ResilientDnsResolver for why
        | that is not paranoia.
        */
        $this->app->bind(DnsResolver::class, ResilientDnsResolver::class);

        // One per request: the header, the footer and the page body share its
        // section data, so the category list is fetched once and not three
        // times on a page that shows it in all three places.
        $this->app->scoped(PageRenderer::class);
    }

    public function boot(): void
    {
        // Storefront paginator: the bundled Tailwind view hardcodes its own
        // palette and LTR arrows, so it ignores the merchant's theme.
        Paginator::defaultView('vendor.pagination.storefront');
        Paginator::defaultSimpleView('vendor.pagination.storefront');

        // Fail loudly on a missing relationship instead of silently issuing an
        // extra query per row — a storefront list is the worst place to find out.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        /*
        | Checkout is the one public endpoint that costs the merchant money.
        |
        | `PlaceOrder` bills the store the moment an order is created, so an
        | unthrottled checkout is not a spam problem — it is a way to drain a
        | competitor's wallet until their balance drops past the overdraft
        | floor and their shop stops accepting real orders. Five thousand
        | requests is a few minutes of scripting.
        |
        | Keyed by IP *and* shop: a shopping mall, a company office or an
        | Egyptian mobile carrier can put hundreds of genuine customers behind
        | one address, and a global per-IP limit would shut a real shop rather
        | than an attacker. Twenty an hour is far above any honest buyer and
        | far below anything that can do damage.
        |
        | The shop is identified by hostname, not by the resolved Store model:
        | `throttle` carries a framework priority that puts it ahead of the
        | custom `store` middleware, so the model is not attached yet when this
        | runs. The Host header is what identifies a storefront anyway — it is
        | how ResolveStore finds the shop in the first place.
        */
        RateLimiter::for('checkout', fn (Request $request) => Limit::perHour(20)
            ->by($request->ip() . '|' . $request->getHost())
            ->response(fn () => back()
                ->withInput()
                ->withErrors(['product_id' => 'طلبات كتير من نفس المكان في وقت قصير. استنى شوية وجرّب تاني.'])));

        /*
        | The storefront's chrome comes from the builder too.
        |
        | A composer rather than a line in each controller: `thanks` and
        | `search` render the same layout, and a header that is configurable on
        | four pages out of six is a bug report waiting to be filed.
        */
        View::composer('storefront.layout', function ($view) {
            $store = $view->getData()['store'] ?? null;

            if ($store instanceof Store) {
                $view->with(app(PageRenderer::class)->chrome(request(), $store));
            }
        });
    }
}

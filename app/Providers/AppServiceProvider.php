<?php

namespace App\Providers;

use App\Models\Store;
use App\Services\Builder\PageRenderer;
use App\Services\Dns\DnsResolver;
use App\Services\Dns\SystemDnsResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bound as an interface so tests can swap in a fake zone file instead of
        // making real DNS queries, which are slow and flaky in CI.
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);

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

<?php

namespace App\Providers;

use App\Models\Store;
use App\Services\Builder\PageRenderer;
use App\Services\Dns\DnsResolver;
use App\Services\Dns\ResilientDnsResolver;
use App\Services\Storefront\StoreResolver;
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
        | The shop is resolved from the Host here rather than read off the
        | request: `throttle` carries a framework priority that puts it ahead
        | of the custom `store` middleware, so the model is not attached yet.
        | Resolved, not the raw Host, because one shop answers on many names —
        | `slug.matgarpro.com`, its trailing-dot and `www.` forms, every custom
        | domain — and keying on the string gave a script a fresh twenty for
        | each spelling.
        |
        | IPv6 is counted per /64. That is one subscriber's allocation, and a
        | single machine can rotate through billions of addresses inside it.
        */
        RateLimiter::for('checkout', fn (Request $request) => Limit::perHour(20)
            ->by(self::clientNetwork((string) $request->ip()) . '|' . self::shopKey($request))
            ->response(fn () => back()
                ->withInput()
                ->withErrors(['product_id' => 'طلبات كتير من نفس المكان في وقت قصير. استنى شوية وجرّب تاني.'])));

        /*
        | The AI store builder, per network per day.
        |
        | Each store gets two runs (config/ai.php), and a store costs nothing
        | but an email to open — so without this, the cap per store is no cap
        | at all for someone signing up again and again. Generous on purpose:
        | Egyptian mobile carriers put whole neighbourhoods behind one address,
        | and twenty runs on Haiku is still under ten cents.
        */
        RateLimiter::for('ai-store-builder', fn (Request $request) => Limit::perDay(20)
            ->by(self::clientNetwork((string) $request->ip()))
            ->response(fn () => response()->json(
                ['message' => 'المتاجر اللي اتجهّزت من نفس المكان النهارده كتير. جرّب تاني بكرة.'],
                429,
            )));

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

    /** The shop a storefront request is for, however its hostname is spelled. */
    private static function shopKey(Request $request): string
    {
        $resolver = app(StoreResolver::class);

        return 'store:' . ($resolver->resolve($request->getHost())?->id
            ?? $resolver->normalizeHost($request->getHost()));
    }

    /** An IPv4 address as is; an IPv6 address reduced to its /64. */
    private static function clientNetwork(string $ip): string
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $ip;
        }

        $packed = inet_pton($ip);

        return $packed === false ? $ip : inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
    }
}

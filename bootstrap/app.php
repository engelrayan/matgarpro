<?php

use App\Http\Middleware\BuilderPreview;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsureAdminIsSuper;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfAdmin;
use App\Http\Middleware\ResolveStore;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        /*
        | Two route trees share one application, separated by hostname.
        |
        | The dashboard MUST be domain-constrained. Without it its `/` is
        | registered first and swallows the home page of every storefront —
        | merchants would point their domain at us and get our login screen.
        */
        then: function () {
            $dashboard = Route::middleware('web');

            if ($appDomain = config('storefront.app_domain')) {
                $dashboard = $dashboard->domain($appDomain);
            }

            $dashboard->group(base_path('routes/web.php'));

            // Everything else: a merchant's own domain or {slug}.matgarpro.com.
            // `store` resolves the tenant and 404s on an unknown hostname;
            // `builder.preview` then decides whether this particular request
            // gets the published layout or the merchant's unsaved draft.
            Route::middleware(['web', 'store', 'builder.preview'])
                ->group(base_path('routes/storefront.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        /*
        | Cookies written by someone other than Laravel.
        |
        | Laravel encrypts every cookie it sets and silently discards any
        | incoming cookie whose signature does not verify. `_fbp` and `_fbc`
        | are written by Meta's pixel, and `mv` by our own tracking script, so
        | without this exception they always read back as null: the ad click
        | identifiers never reach the Conversions API and match quality
        | collapses, with nothing anywhere reporting an error.
        */
        $middleware->encryptCookies(except: ['_fbp', '_fbc', 'mv']);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'store' => ResolveStore::class,
            'builder.preview' => BuilderPreview::class,
            'admin.active' => EnsureAdminIsActive::class,
            'admin.super' => EnsureAdminIsSuper::class,
            'guest.admin' => RedirectIfAdmin::class,
        ]);

        /*
        | Two front doors, and an unauthenticated request must be sent to its
        | own one. The default sends everybody to the merchant login, which for
        | an operator means signing in and landing in a dashboard for a store
        | they do not own, with no hint that /admin was ever the destination.
        */
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*')
            ? route('admin.login')
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

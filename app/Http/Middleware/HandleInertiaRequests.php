<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                /*
                 | The operator, when there is one. Hand-picked fields rather
                 | than the model: `$request->user()` is fine to serialise
                 | whole because a merchant is looking at their own row, but
                 | this one is shared into every page and has no business
                 | carrying login IPs into a payload.
                 */
                'admin' => fn () => $request->user('admin') ? [
                    'id' => $request->user('admin')->id,
                    'name' => $request->user('admin')->name,
                    'email' => $request->user('admin')->email,
                    'role' => $request->user('admin')->role,
                    'role_label' => $request->user('admin')->roleLabel(),
                    'is_super' => $request->user('admin')->isSuper(),
                ] : null,
            ],
            /*
             | One-off messages from the last request.
             |
             | Lazily resolved so the value is read at render time rather than
             | when the array is built — a redirect's flash is set after the
             | middleware has already run.
             */
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
                // The outcome of a batch hand-over to Daman: how many went, and
                // the reason for each one that did not. A count alone would
                // leave the merchant hunting for which orders to fix.
                'daman_result' => fn () => $request->session()->get('daman_result'),
            ],
        ]);
    }
}

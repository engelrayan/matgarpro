<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a signed-in operator off the admin login screen.
 *
 * Laravel's own `guest` middleware would bounce them to the merchant
 * dashboard, which is the wrong app entirely.
 */
class RedirectIfAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.overview');
        }

        return $next($request);
    }
}

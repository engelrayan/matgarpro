<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the routes that change the rules rather than apply them: pricing
 * plans, and who is an operator at all.
 *
 * A 404 rather than a 403 — a support account has no business learning that
 * these screens exist.
 */
class EnsureAdminIsSuper
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Auth::guard('admin')->user()?->isSuper(), 404);

        return $next($request);
    }
}

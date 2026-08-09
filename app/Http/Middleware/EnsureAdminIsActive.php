<?php

namespace App\Http\Middleware;

use App\Services\Admin\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivation takes effect on the next request, not the next login.
 *
 * `auth:admin` only proves the session is signed; it says nothing about
 * whether that account is still allowed in. Without this, revoking an operator
 * would leave their open tab working until they happened to log out — which is
 * precisely the window that matters when somebody leaves in a hurry.
 */
class EnsureAdminIsActive
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin && ! $admin->is_active) {
            $this->audit->log(
                action: 'admin.revoked_session',
                summary: "تم إنهاء جلسة {$admin->name} لأن الحساب موقوف.",
                subject: $admin,
                actor: $admin,
            );

            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->with('error', 'الحساب ده موقوف. كلّم المدير العام.');
        }

        return $next($request);
    }
}

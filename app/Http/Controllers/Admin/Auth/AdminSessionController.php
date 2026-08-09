<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminSessionController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(Request $request): Response
    {
        // No "forgot password" link and no registration link: neither exists,
        // and offering either would be the first thing an attacker probes.
        return Inertia::render('admin/Login', [
            'error' => $request->session()->get('error'),
        ]);
    }

    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // New session id after the privilege level changes — otherwise a
        // session id an attacker planted before login is now an admin session.
        $request->session()->regenerate();

        $admin = Auth::guard('admin')->user();

        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $this->audit->log(
            action: 'admin.logged_in',
            summary: "{$admin->name} سجّل دخول للوحة.",
            subject: $admin,
        );

        return redirect()->intended(route('admin.overview', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        if ($admin) {
            $this->audit->log(
                action: 'admin.logged_out',
                summary: "{$admin->name} خرج من اللوحة.",
                subject: $admin,
                actor: $admin,
            );
        }

        Auth::guard('admin')->logout();

        /*
         | The whole session goes, not just this guard's slice. An operator who
         | happens to also be signed in as a merchant in the same browser gets
         | logged out of both — mildly annoying, and the right trade when the
         | alternative is leaving anything behind on a shared machine.
         */
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}

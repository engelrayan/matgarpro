<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who may enter the panel. Super-admin only.
 *
 * Three invariants hold this together, and each exists because breaking it is
 * unrecoverable from inside the app:
 *
 *  1. Nobody edits their own role or status. Otherwise the screen is a way to
 *     lock yourself out, and the only fix is a shell.
 *  2. The last active super cannot be demoted or deactivated. Same reason.
 *  3. Accounts are deactivated, never deleted — deleting one detaches its
 *     audit trail from a name, and the trail is the point.
 */
class AdminController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('admin/Admins', [
            'admins' => Admin::withCount('activityLogs')
                ->orderByDesc('is_active')->orderBy('name')->get()
                ->map(fn (Admin $admin) => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                    'role_label' => $admin->roleLabel(),
                    'is_active' => $admin->is_active,
                    'actions_count' => $admin->activity_logs_count,
                    'last_login_at' => $admin->last_login_at?->format('Y-m-d H:i'),
                    'last_login_ip' => $admin->last_login_ip,
                    'created_at' => $admin->created_at->format('Y-m-d'),
                ]),
            'roles' => [
                ['value' => Admin::ROLE_SUPER, 'label' => 'مدير عام', 'hint' => 'كل حاجة، بما فيها المشرفين والخطط.'],
                ['value' => Admin::ROLE_STAFF, 'label' => 'فريق الدعم', 'hint' => 'يشوف كل حاجة ويشتغل على المتاجر، مش بيغيّر القواعد.'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')],
            // Long and mixed: this password opens a panel that can suspend
            // every store on the platform.
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()->symbols()],
            'role' => ['required', Rule::in(Admin::ROLES)],
        ]);

        $admin = new Admin;
        // forceFill because `role` and `is_active` are guarded on the model —
        // the one place they may be set is here, from validated input.
        $admin->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => true,
        ])->save();

        $this->audit->log(
            action: 'admin.created',
            summary: "أضاف مشرف جديد {$admin->email} بدور {$admin->roleLabel()}.",
            subject: $admin,
            changes: ['role' => ['from' => null, 'to' => $admin->role]],
        );

        return back()->with('status', 'admin-created');
    }

    public function update(Request $request, Admin $admin): RedirectResponse
    {
        $this->assertNotSelf($request, $admin);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(Admin::ROLES)],
            'is_active' => ['required', 'boolean'],
        ]);

        $losingSuper = $admin->isSuper()
            && ($validated['role'] !== Admin::ROLE_SUPER || ! $validated['is_active']);

        if ($losingSuper && $this->activeSupers() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'ده آخر مدير عام شغّال. عيّن واحد تاني الأول، وإلا مش هيبقى فيه حد يدخل على الشاشات دي.',
            ]);
        }

        $before = $admin->only(['name', 'role', 'is_active']);

        $admin->forceFill($validated)->save();

        $this->audit->log(
            action: 'admin.updated',
            summary: "عدّل صلاحيات {$admin->email} — الدور: {$admin->roleLabel()}، الحالة: " . ($admin->is_active ? 'شغّال' : 'موقوف') . '.',
            subject: $admin,
            changes: $this->audit->diff($before, $admin->only(['name', 'role', 'is_active'])),
        );

        return back()->with('status', 'admin-updated');
    }

    /** Set someone else's password — for a leaver's handover or a lost one. */
    public function password(Request $request, Admin $admin): RedirectResponse
    {
        $this->assertNotSelf($request, $admin);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()->symbols()],
        ]);

        $admin->forceFill(['password' => Hash::make($validated['password'])])->save();

        $this->audit->log(
            action: 'admin.password_reset',
            summary: "غيّر باسورد المشرف {$admin->email}.",
            subject: $admin,
        );

        return back()->with('status', 'admin-password-updated');
    }

    private function assertNotSelf(Request $request, Admin $admin): void
    {
        abort_if($request->user('admin')->is($admin), 403, 'مش هتقدر تعدّل حسابك من هنا.');
    }

    private function activeSupers(): int
    {
        return Admin::where('role', Admin::ROLE_SUPER)->where('is_active', true)->count();
    }
}

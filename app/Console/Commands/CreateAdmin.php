<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Services\Admin\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

/**
 * The only way an operator account comes into existence from nothing.
 *
 * There is no admin registration page and no admin password reset email, so
 * the panel cannot be entered by anyone who does not already have shell access
 * to the server. That is the whole security model in one sentence — resist the
 * temptation to add a web route that does this.
 *
 * Also used to re-provision a password: run it with an existing email and it
 * updates that account instead of failing on the unique index.
 */
class CreateAdmin extends Command
{
    protected $signature = 'admin:create
        {--name= : الاسم}
        {--email= : الإيميل}
        {--password= : الباسورد (لو مكتوبتش هيتطلب بشكل مخفي)}
        {--role=super : super أو staff}';

    protected $description = 'إنشاء مشرف للوحة التحكم العامة أو تحديث باسورد مشرف موجود';

    public function handle(AuditLogger $audit): int
    {
        $name = $this->option('name') ?: $this->ask('الاسم');
        $email = $this->option('email') ?: $this->ask('الإيميل');
        // `secret()` so the password never lands in the shell history file.
        $password = $this->option('password') ?: $this->secret('الباسورد');
        $role = $this->option('role');

        $validator = Validator::make(compact('name', 'email', 'password', 'role'), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(12)->letters()->numbers()->symbols()],
            'role' => ['required', Rule::in(Admin::ROLES)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existing = Admin::where('email', $email)->first();

        if ($existing) {
            if (! $this->confirm("فيه مشرف بالإيميل ده ({$existing->name}). أحدّث الباسورد والدور؟", true)) {
                return self::FAILURE;
            }

            $existing->forceFill([
                'name' => $name,
                'password' => Hash::make($password),
                'role' => $role,
                'is_active' => true,
            ])->save();

            $audit->log(
                action: 'admin.reprovisioned',
                summary: "تم تحديث بيانات المشرف {$existing->email} من سطر الأوامر.",
                subject: $existing,
                changes: ['role' => ['from' => $existing->getOriginal('role'), 'to' => $role]],
            );

            $this->info("تم تحديث المشرف {$existing->email}.");

            return self::SUCCESS;
        }

        $admin = new Admin;
        $admin->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
            'is_active' => true,
        ])->save();

        $audit->log(
            action: 'admin.created',
            summary: "تم إنشاء المشرف {$admin->email} بدور {$admin->roleLabel()} من سطر الأوامر.",
            subject: $admin,
        );

        $this->info("تم إنشاء المشرف {$admin->email} بدور {$admin->roleLabel()}.");
        $this->line('ادخل من: ' . url('/admin/login'));

        return self::SUCCESS;
    }
}

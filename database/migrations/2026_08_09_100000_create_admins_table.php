<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform operators, in their own table.
     *
     * Deliberately NOT a flag on `users`. A merchant account and an operator
     * account are different things with different threat models: merchants
     * self-register, reset their own passwords by email and log in from
     * anywhere. Sharing one table means every one of those flows is one bug
     * away from touching the panel that can suspend stores and move money.
     *
     * There is also no `admin_password_reset_tokens` table on purpose — an
     * operator who loses their password is re-provisioned from the server with
     * `php artisan admin:create`, so there is no email-driven path into the
     * panel at all.
     */
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');

            /*
             | super  — everything, including managing other operators, plans
             |          and anything that changes what stores are charged.
             | staff  — day-to-day support: read the numbers, suspend or
             |          reactivate a store, adjust a wallet, fix a domain.
             |
             | A string rather than an enum: adding a third role should not
             | require an ALTER on a table this sensitive.
             */
            $table->string('role', 20)->default('staff');

            /*
             | Revocation without deletion. Deleting an operator would orphan
             | their audit trail, and the trail is the whole point — a leaver
             | is deactivated, and every action they ever took stays readable.
             */
            $table->boolean('is_active')->default(true);

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index(['is_active', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};

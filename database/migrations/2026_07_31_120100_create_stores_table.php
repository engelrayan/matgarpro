<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            // Free sub-domain forever: {slug}.{config('storefront.domain')}.
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('currency', 3)->default('EGP');

            $table->enum('status', ['draft', 'active', 'suspended'])->default('draft');
            $table->string('suspension_reason')->nullable();

            // ---- Billing ----------------------------------------------------
            $table->foreignId('billing_plan_id')->nullable()->constrained()->nullOnDelete();

            /*
             | Per-store price that beats the plan. Nullable on purpose: NULL
             | means "inherit the plan", 0.00 means "this store pays nothing".
             | A non-nullable column could not tell those two apart.
             */
            $table->decimal('price_per_order_override', 8, 2)->nullable();

            /*
             | Cached wallet balance. Authoritative history lives in
             | store_wallet_transactions — this column exists so the storefront
             | can gate an order without summing the ledger on every request,
             | and it is only ever written inside the same transaction as a
             | ledger row.
             */
            $table->decimal('balance', 12, 2)->default(0);
            $table->enum('billing_status', ['active', 'grace', 'suspended'])->default('active');

            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('billing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};

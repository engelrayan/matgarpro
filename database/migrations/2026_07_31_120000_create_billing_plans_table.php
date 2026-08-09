<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // What a single order costs the merchant. 0 = a genuinely free plan;
            // it is an ordinary price, not a special case anywhere in the code.
            $table->decimal('price_per_order', 8, 2)->default(0);

            // Charged up front on renewal; 0 for pure pay-as-you-go plans.
            $table->decimal('monthly_fee', 10, 2)->default(0);

            // Orders that cost nothing before per-order pricing starts. Lets us
            // run "first 100 orders free" without a second pricing mechanism.
            $table->unsignedInteger('included_orders_monthly')->default(0);

            /*
             | Which moment in an order's life is billable. We start on `created`
             | but the column exists from day one: the day someone floods a store
             | with junk orders, moving to `confirmed` must be a settings change,
             | not a migration.
             */
            $table->enum('billable_event', ['created', 'confirmed', 'delivered'])
                ->default('created');

            $table->boolean('is_default')->default(false);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_plans');
    }
};

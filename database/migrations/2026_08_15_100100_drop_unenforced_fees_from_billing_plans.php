<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes two fields the billing engine never read.
 *
 * `monthly_fee` and `included_orders_monthly` were editable in the admin panel
 * and displayed on the plan card, but nothing charged a subscription and
 * nothing exempted the included orders — every order was billed at the plan's
 * per-order price regardless. An operator setting them got no error and no
 * effect, which is worse than not having the field at all.
 *
 * We sell one thing: a price per order, after a free trial. No monthly
 * subscription and no percentage of sales — that is the offer on the landing
 * page, and now it is the only offer the system can express.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_plans', function (Blueprint $table): void {
            $table->dropColumn(['monthly_fee', 'included_orders_monthly']);
        });
    }

    public function down(): void
    {
        Schema::table('billing_plans', function (Blueprint $table): void {
            $table->decimal('monthly_fee', 10, 2)->default(0)->after('price_per_order');
            $table->unsignedInteger('included_orders_monthly')->default(0)->after('monthly_fee');
        });
    }
};

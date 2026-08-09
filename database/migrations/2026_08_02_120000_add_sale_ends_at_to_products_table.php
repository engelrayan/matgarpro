<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            /*
             | When this product's sale price stops.
             |
             | The storefront counts down to it. That countdown is only honest
             | if it points at a date the merchant actually set — a timer that
             | resets on every page load is the oldest trick in the book, and
             | it teaches customers to distrust every timer on the store.
             |
             | NULL means "on sale with no deadline": still discounted, just no
             | countdown drawn.
             */
            $table->timestamp('sale_ends_at')->nullable()->after('compare_at_price');

            // Powers "which deals are still live" on the home page.
            $table->index(['store_id', 'sale_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'sale_ends_at']);
            $table->dropColumn('sale_ends_at');
        });
    }
};

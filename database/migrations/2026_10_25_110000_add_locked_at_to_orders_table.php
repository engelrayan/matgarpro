<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | When the order arrived while the store was past its credit floor.
         |
         | The order is real — stock is taken, the customer saw "we got your
         | order" — but its customer details stay hidden from the merchant, and
         | it is not charged, until a top-up covers it. Null for every order a
         | merchant can see, which is every order that existed before this.
         */
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('status');
            $table->index(['store_id', 'locked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'locked_at']);
            $table->dropColumn('locked_at');
        });
    }
};

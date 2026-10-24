<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | Quantity offers: "اشتري 3 قطع بـ 600". Each one fixes how many pieces
         | and what each piece costs; the customer picks one on the product
         | page and the order is priced from this row, never from the form.
         */
        Schema::create('product_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->string('title', 120)->nullable();
            $table->string('badge', 40)->nullable();
            $table->string('note', 120)->nullable();
            $table->boolean('free_shipping')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Which offer an order line was sold under, as the customer read it.
        // A snapshot, like the line's name: the offer may be edited tomorrow.
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('offer_label', 160)->nullable()->after('variant_label');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('offer_label');
        });

        Schema::dropIfExists('product_offers');
    }
};

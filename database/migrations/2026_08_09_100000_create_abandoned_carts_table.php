<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A customer who started the order form and never finished.
     *
     * In cash-on-delivery these are the cheapest sales a merchant will ever
     * make: the person already picked a product and typed their number. One
     * WhatsApp message recovers a meaningful share of them, and today that
     * intent is simply lost when they close the tab.
     */
    public function up(): void
    {
        Schema::create('abandoned_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()
                ->constrained('product_variants')->nullOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            /*
             | Whatever they had typed when they stopped. All nullable: the
             | whole point is a half-filled form, and a cart with only a phone
             | is still worth a call.
             */
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 32)->nullable();
            $table->string('governorate')->nullable();

            /*
             | The anonymous browser id the funnel already assigns. One cart per
             | visitor per product, so a customer who retypes their number six
             | times leaves one row, not six.
             */
            $table->string('visitor_id', 40);

            // Set when an order from the same visitor or phone arrives. Kept
            // rather than deleted, so "how many did we recover" is answerable.
            $table->foreignId('recovered_order_id')->nullable()
                ->constrained('orders')->nullOnDelete();
            $table->timestamp('recovered_at')->nullable();

            // When the merchant last messaged them, so the list can stop
            // showing someone who was already contacted an hour ago.
            $table->timestamp('contacted_at')->nullable();

            $table->timestamps();

            $table->unique(['store_id', 'visitor_id', 'product_id'], 'abandoned_carts_visitor_product_unique');
            // Drives the list: this store's open carts, newest first.
            $table->index(['store_id', 'recovered_at', 'updated_at']);
            // Matching an incoming order back to a cart by phone.
            $table->index(['store_id', 'customer_phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_carts');
    }
};

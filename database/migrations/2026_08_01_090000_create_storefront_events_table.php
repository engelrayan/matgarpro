<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            /*
             | The storefront funnel. Without these rows a conversion rate is
             | not computable at all — an order count on its own can never say
             | how many people saw the page and left.
             |
             |   view            → product page opened
             |   checkout_start  → customer began filling the order form
             |   order           → order actually placed
             */
            $table->enum('type', ['view', 'checkout_start', 'order']);

            /*
             | Anonymous per-browser id from a first-party cookie. Lets us count
             | unique visitors and tie a view to the order it became, without
             | storing anything that identifies a person.
             */
            $table->string('visitor_id', 40);

            // Kept only to separate real traffic from obvious bots later. No
            // IP is stored: it is personal data we have no use for here.
            $table->string('user_agent', 512)->nullable();
            $table->string('referrer', 512)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // The dashboard always asks "this store, this type, this window".
            $table->index(['store_id', 'type', 'created_at']);
            $table->index(['store_id', 'product_id', 'type']);
            // De-duplicating a visitor's repeated views of the same page.
            $table->index(['store_id', 'visitor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_events');
    }
};

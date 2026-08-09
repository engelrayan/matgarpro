<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            // Unique per store, not globally: two merchants may both sell a
            // "t-shirt" and neither should have to pick a worse URL for it.
            $table->string('slug');
            $table->text('description')->nullable();

            $table->decimal('price', 10, 2);
            // Shown struck through next to the price. Nullable because "no sale"
            // and "sale at 0" are different things.
            $table->decimal('compare_at_price', 10, 2)->nullable();
            // What the merchant pays. Never leaves the dashboard — it is what
            // makes a real profit figure possible instead of revenue theatre.
            $table->decimal('cost', 10, 2)->nullable();

            $table->string('sku')->nullable();

            /*
             | Stock is opt-out. A merchant selling made-to-order or unlimited
             | digital goods turns it off rather than typing a fake big number,
             | which would otherwise silently start blocking orders one day.
             */
            $table->boolean('track_stock')->default(true);
            $table->integer('stock')->default(0);

            /*
             | Option definitions: [{"name":"اللون","values":["أحمر","أزرق"]}].
             | The concrete combinations live in product_variants; this column
             | is what the storefront renders as selectors and the order in
             | which it renders them.
             */
            $table->json('options')->nullable();

            $table->enum('status', ['draft', 'active'])->default('active');
            $table->unsignedInteger('sort_order')->default(0);

            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'slug']);
            $table->index(['store_id', 'status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

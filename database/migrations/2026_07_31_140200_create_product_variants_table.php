<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            /*
             | One concrete combination: {"اللون":"أحمر","المقاس":"L"}. Stored as
             | the option names themselves rather than ids — a merchant renaming
             | "اللون" is renaming the thing customers already ordered, so the
             | historical order line must keep the words that were on screen.
             */
            $table->json('options');

            // NULL means "same as the product". Only variants that actually cost
            // something different carry a number, so a price edit on the product
            // does not silently leave variants behind at the old price.
            $table->decimal('price', 10, 2)->nullable();

            $table->integer('stock')->default(0);
            $table->string('sku')->nullable();

            $table->foreignId('image_id')->nullable()
                ->constrained('product_images')->nullOnDelete();

            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};

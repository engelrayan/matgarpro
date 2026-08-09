<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Per-store counter starting at 1. Merchants read their orders out
            // loud on the phone; a global id would leak how many orders the
            // whole platform has and give the first merchants ugly numbers.
            $table->unsignedInteger('number');

            $table->string('customer_name');
            $table->string('customer_phone', 32);
            $table->string('customer_phone_alt', 32)->nullable();
            $table->string('customer_email')->nullable();

            $table->string('governorate')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->text('note')->nullable();

            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);

            /*
             | `pending` is deliberately the entry state even though nothing is
             | verified yet: COD orders in this market are confirmed by a human
             | or a WhatsApp reply before they are worth shipping.
             */
            $table->enum('status', [
                'pending', 'confirmed', 'shipped', 'delivered', 'cancelled', 'returned',
            ])->default('pending');

            $table->enum('payment_method', ['cod'])->default('cod');

            // Kept for the fake-order and repeat-refuser work later. Recorded
            // now because it cannot be backfilled after the fact.
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['store_id', 'number']);
            $table->index(['store_id', 'status', 'created_at']);
            // Powers "has this phone ordered from us before?" on the order screen.
            $table->index(['store_id', 'customer_phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

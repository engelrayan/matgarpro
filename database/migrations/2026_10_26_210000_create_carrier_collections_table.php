<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | Money a carrier handed over for delivered orders.
         |
         | Every order is paid cash on delivery, so for a while the money for a
         | delivered parcel sits with the carrier. A collection is the merchant
         | recording that a carrier paid them: how much arrived, for which
         | orders, and what those orders should have come to — the gap is the
         | carrier's fees, or a shortfall worth a phone call.
         */
        Schema::create('carrier_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // As Daman names the carrier on the order; null for orders the
            // merchant shipped without Daman, with their own courier.
            $table->string('carrier', 120)->nullable();

            $table->decimal('amount', 12, 2);
            $table->decimal('expected_amount', 12, 2);
            $table->unsignedInteger('orders_count');
            $table->date('received_on');
            $table->string('reference', 120)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['store_id', 'received_on']);
        });

        Schema::table('orders', function (Blueprint $table) {
            // Which collection paid for this order; null while its money is
            // still with the carrier. Set null if the collection is undone.
            $table->foreignId('carrier_collection_id')->nullable()->after('daman_error')
                ->constrained('carrier_collections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carrier_collection_id');
        });

        Schema::dropIfExists('carrier_collections');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            /*
             | What Daman answered when this order was handed over.
             |
             | Two different numbers, and the merchant needs both: the Daman
             | order number is what support asks for, and the tracking number is
             | the carrier's own waybill — the one printed on the parcel and the
             | only one the courier's call centre recognises.
             */
            $table->string('daman_order_number', 40)->nullable()->after('tracking');
            $table->unsignedBigInteger('daman_shipment_id')->nullable()->after('daman_order_number');
            $table->string('daman_tracking_number', 60)->nullable()->after('daman_shipment_id');
            $table->string('daman_carrier_name', 120)->nullable()->after('daman_tracking_number');

            // Daman's own status, kept verbatim next to our own. Ours is what
            // the merchant filters by; this is what actually happened at the
            // door, and the two are worth being able to compare.
            $table->string('daman_status', 40)->nullable()->after('daman_carrier_name');
            $table->string('daman_status_note', 190)->nullable()->after('daman_status');

            $table->timestamp('daman_sent_at')->nullable()->after('daman_status_note');

            // Why the last hand-over failed — shown on the row, so a rejected
            // governorate or a credit limit is visible without opening a log.
            $table->string('daman_error', 500)->nullable()->after('daman_sent_at');

            // Webhooks arrive keyed by Daman's shipment id; this is the lookup.
            $table->index('daman_shipment_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['daman_shipment_id']);
            $table->dropColumn([
                'daman_order_number',
                'daman_shipment_id',
                'daman_tracking_number',
                'daman_carrier_name',
                'daman_status',
                'daman_status_note',
                'daman_sent_at',
                'daman_error',
            ]);
        });
    }
};

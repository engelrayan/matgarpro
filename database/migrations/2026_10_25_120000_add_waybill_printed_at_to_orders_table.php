<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | When this order's waybill was first opened for printing.
         |
         | What "print the ones I haven't printed" reads. Set when the waybill
         | page renders — the browser's print dialog tells the server nothing,
         | so opening the labels is the closest honest signal there is.
         */
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('waybill_printed_at')->nullable()->after('locked_at');
            $table->index(['store_id', 'waybill_printed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'waybill_printed_at']);
            $table->dropColumn('waybill_printed_at');
        });
    }
};

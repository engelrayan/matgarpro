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
             | Ad-attribution context captured at checkout: `fbp` (the browser
             | cookie Meta sets) and `fbc` (derived from the `fbclid` on the ad
             | click), plus the landing URL.
             |
             | Stored on the order because the Conversions API call runs in a
             | queued job, long after the request that had access to the
             | cookies. Without it, match quality collapses and Meta cannot tie
             | the sale back to the ad that produced it.
             */
            $table->json('tracking')->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('tracking');
        });
    }
};

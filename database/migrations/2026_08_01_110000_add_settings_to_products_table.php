<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            /*
             | Per-product page behaviour: button wording, sticky buy bar,
             | section order, free shipping, and what happens at zero stock.
             |
             | A JSON column rather than a column per toggle — these are page
             | presentation flags that will keep growing, none of them are ever
             | queried or aggregated, and a migration per checkbox is not a
             | trade worth making.
             */
            $table->json('settings')->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};

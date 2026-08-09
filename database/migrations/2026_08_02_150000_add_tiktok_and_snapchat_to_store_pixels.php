<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Merchants in this market run the same product on Facebook, TikTok and
     * Snapchat at once. A pixel table that only knows Meta forces them to keep
     * the other two somewhere else, which is where tracking quietly stops.
     *
     * Raw ALTER rather than a Blueprint change: doctrine/dbal does not
     * round-trip MySQL enums, and altering the column through it would silently
     * rewrite it as a varchar.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE store_pixels MODIFY provider ENUM('meta','tiktok','snapchat') NOT NULL");
    }

    public function down(): void
    {
        // Rows for the new providers would violate the narrower enum.
        DB::table('store_pixels')->whereIn('provider', ['tiktok', 'snapchat'])->delete();

        DB::statement("ALTER TABLE store_pixels MODIFY provider ENUM('meta') NOT NULL");
    }
};

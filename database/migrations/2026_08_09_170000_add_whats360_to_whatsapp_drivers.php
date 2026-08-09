<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A third gateway.
     *
     * Raw SQL rather than a Blueprint change: altering an enum needs DBAL,
     * which this project does not carry, and the statement is clearer than the
     * package would be anyway.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE store_whatsapp_integrations
             MODIFY COLUMN driver ENUM('wapilot', 'cloud_api', 'whats360') NOT NULL DEFAULT 'wapilot'",
        );
    }

    public function down(): void
    {
        // Anyone already on the gateway being removed goes back to the default;
        // dropping it from the enum with rows still holding it would fail.
        DB::table('store_whatsapp_integrations')
            ->where('driver', 'whats360')
            ->update(['driver' => 'wapilot', 'is_active' => false]);

        DB::statement(
            "ALTER TABLE store_whatsapp_integrations
             MODIFY COLUMN driver ENUM('wapilot', 'cloud_api') NOT NULL DEFAULT 'wapilot'",
        );
    }
};

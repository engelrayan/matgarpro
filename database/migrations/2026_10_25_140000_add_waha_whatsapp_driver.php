<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A fifth gateway: the store's own number through our self-hosted WAHA.
     * Raw SQL for the enum, as in the earlier driver migrations.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE store_whatsapp_integrations
             MODIFY COLUMN driver ENUM('wapilot', 'cloud_api', 'whats360', 'platform', 'waha') NOT NULL DEFAULT 'wapilot'",
        );
    }

    public function down(): void
    {
        // Nothing to fall back to: the session lives on our gateway, so the
        // store is switched off rather than moved somewhere it has no keys.
        DB::table('store_whatsapp_integrations')
            ->where('driver', 'waha')
            ->update(['driver' => 'wapilot', 'is_active' => false]);

        DB::statement(
            "ALTER TABLE store_whatsapp_integrations
             MODIFY COLUMN driver ENUM('wapilot', 'cloud_api', 'whats360', 'platform') NOT NULL DEFAULT 'wapilot'",
        );
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The platform's own WhatsApp number, as a fourth "gateway".
     *
     * A store on it holds no credentials of its own — the row only records
     * that the merchant switched it on, and whether new orders send by
     * themselves.
     *
     * `whatsapp_reminded_at` is the one nudge a silent customer gets before the
     * order lands on the merchant's call list; stamped so it is sent once.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE store_whatsapp_integrations
             MODIFY COLUMN driver ENUM('wapilot', 'cloud_api', 'whats360', 'platform') NOT NULL DEFAULT 'wapilot'",
        );

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('whatsapp_reminded_at')->nullable()->after('whatsapp_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('whatsapp_reminded_at');
        });

        // A store on the platform number has no keys of its own to fall back
        // to, so it is switched off rather than moved to another gateway.
        DB::table('store_whatsapp_integrations')
            ->where('driver', 'platform')
            ->update(['driver' => 'wapilot', 'is_active' => false]);

        DB::statement(
            "ALTER TABLE store_whatsapp_integrations
             MODIFY COLUMN driver ENUM('wapilot', 'cloud_api', 'whats360') NOT NULL DEFAULT 'wapilot'",
        );
    }
};

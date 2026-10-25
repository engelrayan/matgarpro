<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Wapilot, Whats360 and a merchant's own Cloud API keys are no longer
     * offered; a store confirms on the platform's official number or on its
     * own number by QR. A link left on one of them would only fail at send
     * time, so it goes now and the store picks again on the WhatsApp page.
     *
     * The enum keeps the old values: dropping them would rewrite the column
     * for nothing, and the message log still names the driver it went out on.
     */
    public function up(): void
    {
        DB::table('store_whatsapp_integrations')
            ->whereIn('driver', ['wapilot', 'whats360', 'cloud_api'])
            ->delete();
    }

    public function down(): void
    {
        // The keys are gone with the rows; there is nothing to put back.
    }
};

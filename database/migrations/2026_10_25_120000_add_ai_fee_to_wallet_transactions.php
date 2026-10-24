<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A paid AI service — the first is a written landing page.
     *
     * Its own type rather than `order_fee` or `adjustment`: the merchant's
     * statement and our revenue report both need to tell "you sold something"
     * from "you bought something from us". Raw SQL for the enum, as in the
     * WhatsApp driver migration, because the project carries no DBAL.
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE store_wallet_transactions
             MODIFY COLUMN type ENUM('topup', 'order_fee', 'subscription_fee', 'refund', 'adjustment', 'ai_fee') NOT NULL",
        );
    }

    public function down(): void
    {
        // Kept as charges, just under a type the old schema knows.
        DB::table('store_wallet_transactions')->where('type', 'ai_fee')->update(['type' => 'adjustment']);

        DB::statement(
            "ALTER TABLE store_wallet_transactions
             MODIFY COLUMN type ENUM('topup', 'order_fee', 'subscription_fee', 'refund', 'adjustment') NOT NULL",
        );
    }
};

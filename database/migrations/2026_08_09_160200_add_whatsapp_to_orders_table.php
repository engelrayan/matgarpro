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
             | Where this order stands in the confirmation conversation.
             |
             | Separate from `status` on purpose: an order the customer never
             | answered is not the same as one the merchant has not looked at,
             | and the merchant's follow-up list is built from exactly that
             | difference.
             |
             | null → never messaged · sent → waiting on the customer
             | confirmed / cancelled → they answered · failed → never delivered
             */
            $table->string('whatsapp_state', 20)->nullable()->after('daman_error');

            $table->timestamp('whatsapp_sent_at')->nullable()->after('whatsapp_state');
            $table->timestamp('whatsapp_replied_at')->nullable()->after('whatsapp_sent_at');
            $table->string('whatsapp_error', 500)->nullable()->after('whatsapp_replied_at');

            // Finding the order a reply belongs to: newest `sent` order for
            // this store and phone.
            $table->index(['store_id', 'whatsapp_state']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'whatsapp_state']);
            $table->dropColumn([
                'whatsapp_state',
                'whatsapp_sent_at',
                'whatsapp_replied_at',
                'whatsapp_error',
            ]);
        });
    }
};

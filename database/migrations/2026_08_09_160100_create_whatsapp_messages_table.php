<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every message in and out, kept.
     *
     * Without it, "الواتساب مش شغال" is unanswerable: the gateway is somebody
     * else's dashboard, the reply arrived at three in the morning, and nobody
     * can say whether we sent, they answered, or we failed to understand them.
     */
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // Inbound messages from a number we cannot match to an order still
            // get a row — that is precisely the case worth looking at.
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('direction', ['out', 'in']);

            // E.164 digits, no plus. Normalised on both sides so an inbound
            // `201006262330` matches an order saved as `01006262330`.
            $table->string('phone', 24);

            $table->text('body')->nullable();

            // The gateway's own id, so a merchant can find the same message in
            // their dashboard.
            $table->string('provider_message_id', 128)->nullable();

            // out: sent|failed · in: received
            $table->string('status', 20);

            // How we read an inbound reply: confirm | cancel | unknown.
            $table->string('intent', 20)->nullable();

            $table->string('error', 500)->nullable();

            $table->timestamps();

            // The two questions actually asked of this table: "what happened to
            // this order" and "what came in from this number".
            $table->index(['store_id', 'phone', 'created_at']);
            $table->index(['order_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_whatsapp_integrations', function (Blueprint $table) {
            $table->id();

            // One WhatsApp number per store. Deliberately not a platform-wide
            // number: the customer has to recognise who is messaging them, and
            // one shared number would collect every store's spam reports until
            // it was blocked for all of them.
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();

            /*
             | Which gateway carries the message.
             |
             | `cloud_api` is Meta's own — official and stable, but a message the
             | business starts must be an approved template. `wapilot` is an
             | unofficial WhatsApp-Web gateway: no templates, no approval, and a
             | real chance of the number being banned. Merchants pick their own
             | trade-off, so both live behind one interface.
             */
            $table->enum('driver', ['wapilot', 'cloud_api'])->default('wapilot');

            // Per-driver keys, encrypted at rest — a token here can send from
            // the merchant's own number, which is their reputation.
            $table->text('credentials')->nullable();

            // Shown back to the merchant so they can tell which number is
            // connected without opening the gateway's dashboard.
            $table->string('sender_phone', 24)->nullable();

            $table->boolean('is_active')->default(true);

            // Whether a new order messages the customer by itself. Off means the
            // merchant sends from the order screen when they choose to.
            $table->boolean('auto_send')->default(true);

            /*
             | The message, as the merchant words it. Placeholders are filled at
             | send time — see OrderConfirmationMessage.
             |
             | Editable because the wording is the merchant's voice, and a
             | platform-written sentence in the wrong tone is the reason a
             | customer does not answer.
             */
            $table->text('message_template')->nullable();

            // Cloud API only: the approved template that carries the message and
            // its two quick-reply buttons.
            $table->string('template_name', 100)->nullable();
            $table->string('template_language', 12)->default('ar');

            /*
             | Inbound replies. The token names the store in the URL; the verify
             | token is what Meta echoes back when it subscribes to the webhook.
             */
            $table->string('webhook_token', 40)->unique();
            $table->string('verify_token', 40);

            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->string('last_error', 500)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_whatsapp_integrations');
    }
};

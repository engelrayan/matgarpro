<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The merchant's WhatsApp inbox: every message to and from one number,
     * gathered into a conversation the team can read, tag, note and answer
     * without picking up the phone that is linked to the store.
     */
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            // E.164 digits, the same shape whatsapp_messages.phone uses.
            $table->string('phone', 24);

            // From the customer's orders when there are any, else their
            // WhatsApp profile name. Either way only a label.
            $table->string('name', 120)->nullable();

            $table->json('tags')->nullable();
            $table->unsignedInteger('unread_count')->default(0);

            // Denormalised so the list is one query, not one per row.
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_preview', 255)->nullable();
            $table->string('last_direction', 4)->nullable();

            $table->text('summary')->nullable();
            $table->timestamp('summary_at')->nullable();

            $table->timestamps();

            $table->unique(['store_id', 'phone']);
            $table->index(['store_id', 'last_message_at']);
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('order_id')
                ->constrained('whatsapp_conversations')->nullOnDelete();

            // text | poll | vote | media
            $table->string('kind', 10)->default('text')->after('direction');

            // Poll options, the WhatsApp profile name, where an outgoing
            // message was written (inbox or the phone itself).
            $table->json('meta')->nullable()->after('body');

            // Who on the team wrote it, for replies sent from the inbox.
            $table->foreignId('user_id')->nullable()->after('conversation_id')
                ->constrained()->nullOnDelete();

            $table->index(['conversation_id', 'id']);
        });

        // Notes are the team talking to itself: never sent, so never in the
        // messages table, where every query is about what the customer saw.
        Schema::create('whatsapp_conversation_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('whatsapp_quick_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('title', 60);
            $table->text('body');
            $table->timestamps();
        });

        $this->gatherExistingMessages();
    }

    /** Messages from before the inbox, filed into their conversations. */
    private function gatherExistingMessages(): void
    {
        $pairs = DB::table('whatsapp_messages')
            ->select('store_id', 'phone', DB::raw('MAX(created_at) as last_at'))
            ->groupBy('store_id', 'phone')
            ->get();

        foreach ($pairs as $pair) {
            $last = DB::table('whatsapp_messages')
                ->where('store_id', $pair->store_id)->where('phone', $pair->phone)
                ->latest('id')->first();

            $id = DB::table('whatsapp_conversations')->insertGetId([
                'store_id' => $pair->store_id,
                'phone' => $pair->phone,
                'name' => DB::table('orders')
                    ->where('store_id', $pair->store_id)
                    ->whereRaw('RIGHT(REPLACE(customer_phone, " ", ""), 9) = ?', [substr($pair->phone, -9)])
                    ->latest('id')->value('customer_name'),
                'last_message_at' => $pair->last_at,
                'last_preview' => mb_substr((string) $last?->body, 0, 250),
                'last_direction' => $last?->direction,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('whatsapp_messages')
                ->where('store_id', $pair->store_id)->where('phone', $pair->phone)
                ->update(['conversation_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_quick_replies');
        Schema::dropIfExists('whatsapp_conversation_notes');

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'id']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('conversation_id');
            $table->dropColumn(['kind', 'meta']);
        });

        Schema::dropIfExists('whatsapp_conversations');
    }
};

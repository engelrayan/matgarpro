<?php

namespace App\Services\AI\Inbox;

use App\Models\AiGeneration;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\AI\AiFeature;
use App\Services\AI\ClaudeGateway;
use App\Services\AI\GeneratesDraft;
use Illuminate\Support\Str;

/**
 * Reads a WhatsApp conversation for the team: what it is about, what is still
 * open, and a reply they could send next.
 *
 * Both answers come from one call — the button that asks for a summary and the
 * one that asks for a reply want the same reading of the same thread. Nothing
 * is sent: the reply goes into the composer, where the merchant edits it and
 * presses send themselves. The summary is kept on the conversation, because it
 * is for the team and costs a call to make again.
 */
class ConversationAssistant implements GeneratesDraft
{
    /** Enough to follow a sale; more costs tokens and buries the latest turn. */
    private const MESSAGES = 40;

    /** The catalogue as context for prices and stock, not a full export. */
    private const PRODUCTS = 80;

    private const SYSTEM = <<<'PROMPT'
        You help the team of an Egyptian online shop answer its customers on WhatsApp.

        You get the shop's products with prices and stock, the customer's orders, the team's
        internal notes, and the latest messages of the conversation, oldest first. Lines marked
        «العميل» are from the customer; «المتجر» lines are from the shop.

        Return two things:

        summary — for the team only, never shown to the customer. Two to four short lines in
        Egyptian Arabic: who the customer is to the shop (new, has orders, which), what they
        want, and what is still unanswered or pending. Plain sentences, no headings, every word in
        Arabic — call the shop «المتجر», never by an English word.

        reply — the next message the shop could send, in friendly Egyptian Arabic, short like a
        WhatsApp message (one to three sentences). Answer what the customer actually asked last.
        Use prices, availability and order status only from the data given; never invent a
        price, a delivery date, a discount or a policy. If the answer needs something you do
        not have, say the shop will check and get back to them. If the last message is from the
        shop and nothing is waiting on the shop, reply with an empty string.
        PROMPT;

    public function __construct(private readonly ClaudeGateway $claude) {}

    public function generate(AiGeneration $generation): array
    {
        $store = $generation->store;
        $conversation = WhatsappConversation::where('store_id', $store->id)
            ->findOrFail((int) ($generation->input['conversation_id'] ?? 0));

        $answer = $this->claude->json(
            $store,
            AiFeature::InboxAssistant,
            self::SYSTEM,
            [['type' => 'text', 'text' => $this->brief($store, $conversation)]],
            [
                'type' => 'object',
                'properties' => ['summary' => ['type' => 'string'], 'reply' => ['type' => 'string']],
                'required' => ['summary', 'reply'],
                'additionalProperties' => false,
            ],
            $generation,
        );

        $summary = Str::limit(trim((string) ($answer['summary'] ?? '')), 1200, '…');
        $reply = Str::limit(trim((string) ($answer['reply'] ?? '')), 1000, '…');

        $conversation->forceFill(['summary' => $summary, 'summary_at' => now()])->save();

        return ['summary' => $summary, 'reply' => $reply];
    }

    private function brief(Store $store, WhatsappConversation $conversation): string
    {
        $messages = $conversation->messages()->latest('id')->limit(self::MESSAGES)->get()->reverse()
            ->map(fn (WhatsappMessage $m) => sprintf(
                '[%s] %s: %s',
                $m->created_at->format('Y-m-d H:i'),
                $m->direction === WhatsappMessage::DIRECTION_IN ? 'العميل' : 'المتجر',
                $m->kind === WhatsappMessage::KIND_VOTE ? 'اختار «' . $m->body . '» في الاستطلاع' : $m->body,
            ))
            ->implode("\n");

        $orders = $conversation->orders()->visible()->with('items')->latest('id')->limit(5)->get()
            ->map(fn (Order $o) => sprintf(
                '#%s · %s · %s %s · %s · %s',
                $o->number,
                $o->created_at->format('Y-m-d'),
                number_format((float) $o->total, 2),
                $store->currency,
                $o->statusLabel(),
                $o->items->map(fn ($i) => $i->quantity . '× ' . $i->name . ($i->variant_label ? " ({$i->variant_label})" : ''))->implode('، '),
            ))
            ->implode("\n");

        $notes = $conversation->notes()->latest('id')->limit(10)->pluck('body')->implode("\n");

        $products = $store->products()->where('status', Product::STATUS_ACTIVE)->latest('id')->limit(self::PRODUCTS)->get()
            ->map(fn (Product $p) => sprintf(
                '%s · %s %s%s',
                $p->name,
                number_format((float) $p->price, 0),
                $store->currency,
                $p->track_stock ? ($p->availableStock() > 0 ? '' : ' · out of stock') : '',
            ))
            ->implode("\n");

        return implode("\n\n", [
            "<shop_name>{$store->name}</shop_name>",
            '<customer_name>' . ($conversation->name ?: '(unknown)') . '</customer_name>',
            '<products>' . ($products ?: '(none)') . '</products>',
            '<customer_orders>' . ($orders ?: '(none)') . '</customer_orders>',
            '<team_notes>' . ($notes ?: '(none)') . '</team_notes>',
            '<conversation>' . ($messages ?: '(empty)') . '</conversation>',
        ]);
    }
}

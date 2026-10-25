<?php

namespace App\Services\Whatsapp;

use App\Jobs\SendWhatsappAcknowledgement;
use App\Models\Order;
use App\Models\StoreWhatsappIntegration;
use App\Models\WhatsappMessage;

/**
 * Turns a customer's reply into a decision on their order.
 *
 * Deliberately narrow: it will only ever move an order that is already waiting
 * on an answer from that exact number. Everything else — a reply to a message
 * about a different order, somebody writing in out of nowhere, a word we did
 * not recognise — is written down and left for a human. The merchant reads
 * their WhatsApp anyway; what they cannot do is un-ship a parcel.
 */
class InboundReplyHandler
{
    public function __construct(private readonly ReplyInterpreter $interpreter) {}

    /** @return array{matched: bool, intent: string, order_id: ?int} */
    public function handle(StoreWhatsappIntegration $link, InboundMessage $message): array
    {
        if ($message->fromMe) {
            $this->recordFromPhone($link, $message);

            return ['matched' => false, 'intent' => WhatsappMessage::INTENT_UNKNOWN, 'order_id' => null];
        }

        $order = $this->awaitingReplyFrom($link, $message->phone);

        // A photo or a voice note says nothing we can read as yes or no.
        $intent = $message->kind === WhatsappMessage::KIND_MEDIA
            ? WhatsappMessage::INTENT_UNKNOWN
            : $this->interpreter->read($message->body);

        WhatsappMessage::create([
            'store_id' => $link->store_id,
            'order_id' => $order?->id,
            'direction' => WhatsappMessage::DIRECTION_IN,
            'kind' => $message->kind ?? ($message->fromButton ? WhatsappMessage::KIND_VOTE : WhatsappMessage::KIND_TEXT),
            'phone' => $message->phone,
            'body' => $message->body,
            'meta' => $message->pushName ? ['push_name' => $message->pushName] : null,
            'provider_message_id' => $message->providerMessageId,
            'status' => 'received',
            'intent' => $intent,
        ]);

        $link->forceFill(['last_inbound_at' => now()])->save();

        if (! $order || $intent === WhatsappMessage::INTENT_UNKNOWN) {
            return ['matched' => false, 'intent' => $intent, 'order_id' => $order?->id];
        }

        $this->apply($order, $intent);

        // "طلبك اتأكد" on the store's own WAHA number, as on the platform's.
        // Not on the third-party gateways: a second unprompted message is
        // what gets those numbers flagged.
        if ($link->isWaha()) {
            SendWhatsappAcknowledgement::dispatch($order->id, $intent);
        }

        return ['matched' => true, 'intent' => $intent, 'order_id' => $order->id];
    }

    /**
     * A reply on the platform's shared number.
     *
     * There is no store in the URL here — one webhook carries every shop's
     * customers — so the order is found from the reply itself: the signed
     * payload on a tapped button first, then the message the customer is
     * answering, and only then the phone number, and only when that number is
     * waiting on exactly one order across the whole platform.
     *
     * @return array{matched: bool, intent: string, order_id: ?int}
     */
    public function handlePlatform(InboundMessage $message): array
    {
        $button = ButtonPayload::read($message->payload);

        $order = $button
            ? $this->awaiting($button['order_id'], $message->phone)
            : $this->platformOrderFor($message);

        // The button says what it means; typed words still go through the
        // dictionary, with the same "unsure means do nothing" rule.
        $intent = $button['intent'] ?? $this->interpreter->read($message->body);

        // Nowhere to write it down: the log is per store, and a message no
        // order of ours is waiting on belongs to no store.
        if (! $order) {
            return ['matched' => false, 'intent' => $intent, 'order_id' => null];
        }

        WhatsappMessage::create([
            'store_id' => $order->store_id,
            'order_id' => $order->id,
            'direction' => WhatsappMessage::DIRECTION_IN,
            'phone' => $message->phone,
            'body' => $message->body,
            'provider_message_id' => $message->providerMessageId,
            'status' => 'received',
            'intent' => $intent,
        ]);

        $order->store->whatsappIntegration?->forceFill(['last_inbound_at' => now()])->save();

        if ($intent === WhatsappMessage::INTENT_UNKNOWN) {
            return ['matched' => false, 'intent' => $intent, 'order_id' => $order->id];
        }

        $this->apply($order, $intent);

        // So the customer knows the tap did something. Queued: the webhook
        // answers Meta now, and a slow send must not make Meta retry it.
        SendWhatsappAcknowledgement::dispatch($order->id, $intent);

        return ['matched' => true, 'intent' => $intent, 'order_id' => $order->id];
    }

    /**
     * The order a platform reply is about, when no button says so.
     */
    private function platformOrderFor(InboundMessage $message): ?Order
    {
        // "Reply" on our message — WhatsApp tells us which one it was.
        if ($message->replyTo) {
            $orderId = WhatsappMessage::query()
                ->where('direction', WhatsappMessage::DIRECTION_OUT)
                ->where('provider_message_id', $message->replyTo)
                ->value('order_id');

            if ($orderId) {
                return $this->awaiting((int) $orderId, $message->phone);
            }
        }

        if ($message->phone === '') {
            return null;
        }

        $candidates = $this->awaitingQuery($message->phone)
            ->whereHas('store.whatsappIntegration', fn ($q) => $q->where('driver', StoreWhatsappIntegration::DRIVER_PLATFORM))
            ->latest('id')
            ->limit(2)
            ->get();

        // One customer, two shops, one shared number: "تمام" could mean either.
        // Guessing would confirm a parcel somebody did not agree to.
        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    /** This order, if it is still waiting on an answer from this number. */
    private function awaiting(int $orderId, string $phone): ?Order
    {
        if ($phone === '') {
            return null;
        }

        return $this->awaitingQuery($phone)->whereKey($orderId)->first();
    }

    /**
     * Orders still waiting on this number's answer. `no_reply` counts: a
     * customer who answers late should still be able to confirm, and is far
     * better than the call the merchant was about to make.
     */
    private function awaitingQuery(string $phone): \Illuminate\Database\Eloquent\Builder
    {
        return Order::query()
            ->whereIn('whatsapp_state', ['sent', 'no_reply'])
            ->where('status', Order::STATUS_PENDING)
            // Same tail match as awaitingReplyFrom(), for the same reason.
            ->whereRaw('RIGHT(REPLACE(customer_phone, " ", ""), 9) = ?', [substr($phone, -9)]);
    }

    /**
     * The order this reply is about.
     *
     * Newest first: a customer who ordered twice in a day is answering the
     * message they just received, not the one from the morning. Restricted to
     * orders still `pending` — once the merchant has confirmed or cancelled one
     * by hand, their decision stands.
     */
    private function awaitingReplyFrom(StoreWhatsappIntegration $link, string $phone): ?Order
    {
        if ($phone === '') {
            return null;
        }

        return Order::query()
            ->where('store_id', $link->store_id)
            ->where('whatsapp_state', 'sent')
            ->where('status', Order::STATUS_PENDING)
            /*
             | Matched on the last nine digits rather than the whole number.
             |
             | The order carries whatever the customer typed into the form —
             | `01006262330`, or the same number with a country code, or with
             | spaces. The webhook always carries E.164. Comparing the tail is
             | what makes those the same person without normalising a column we
             | would then have to keep normalised forever.
             */
            ->whereRaw('RIGHT(REPLACE(customer_phone, " ", ""), 9) = ?', [substr($phone, -9)])
            ->latest('id')
            ->first();
    }

    /**
     * Something the merchant typed on the linked phone itself.
     *
     * WAHA reports our own API sends the same way, so a message we already
     * recorded is skipped — matched on WhatsApp's message key, since the chat
     * part of the id may be a number one time and a hidden @lid id the next.
     */
    private function recordFromPhone(StoreWhatsappIntegration $link, InboundMessage $message): void
    {
        $key = WhatsappMessage::providerKey($message->providerMessageId);

        $known = $key && WhatsappMessage::query()
            ->where('store_id', $link->store_id)
            ->where('phone', $message->phone)
            ->where('direction', WhatsappMessage::DIRECTION_OUT)
            ->where('provider_message_id', 'like', '%' . $key)
            ->exists();

        if ($known) {
            return;
        }

        WhatsappMessage::create([
            'store_id' => $link->store_id,
            'direction' => WhatsappMessage::DIRECTION_OUT,
            'kind' => $message->kind ?? WhatsappMessage::KIND_TEXT,
            'phone' => $message->phone,
            'body' => $message->body,
            'meta' => ['source' => 'phone'],
            'provider_message_id' => $message->providerMessageId,
            'status' => 'sent',
        ]);
    }

    private function apply(Order $order, string $intent): void
    {
        $order->forceFill([
            'status' => $intent === WhatsappMessage::INTENT_CONFIRM
                ? Order::STATUS_CONFIRMED
                : Order::STATUS_CANCELLED,
            'whatsapp_state' => $intent === WhatsappMessage::INTENT_CONFIRM ? 'confirmed' : 'cancelled',
            'whatsapp_replied_at' => now(),
        ])->save();
    }
}

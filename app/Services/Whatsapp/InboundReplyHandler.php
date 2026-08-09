<?php

namespace App\Services\Whatsapp;

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
        $order = $this->awaitingReplyFrom($link, $message->phone);
        $intent = $this->interpreter->read($message->body);

        WhatsappMessage::create([
            'store_id' => $link->store_id,
            'order_id' => $order?->id,
            'direction' => WhatsappMessage::DIRECTION_IN,
            'phone' => $message->phone,
            'body' => $message->body,
            'provider_message_id' => $message->providerMessageId,
            'status' => 'received',
            'intent' => $intent,
        ]);

        $link->forceFill(['last_inbound_at' => now()])->save();

        if (! $order || $intent === WhatsappMessage::INTENT_UNKNOWN) {
            return ['matched' => false, 'intent' => $intent, 'order_id' => $order?->id];
        }

        $this->apply($order, $intent);

        return ['matched' => true, 'intent' => $intent, 'order_id' => $order->id];
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

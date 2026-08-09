<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Messages one customer about one order.
 *
 * Queued, always. The customer's checkout must never wait on a WhatsApp
 * gateway — a slow one would otherwise show up as a slow thank-you page, on the
 * single screen where the merchant most needs the sale to feel finished.
 */
class SendWhatsappOrderConfirmation implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public function __construct(public readonly int $orderId) {}

    /** Minutes apart: a gateway that is down is down for more than a second. */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(WhatsappSender $sender): void
    {
        $order = Order::with('items', 'store')->find($this->orderId);
        $link = $order?->store->whatsappIntegration;

        // Deleted, disconnected, or switched off between queueing and running.
        if (! $order || ! $link?->canSend()) {
            return;
        }

        // Already messaged — a retried job must not send the customer a second
        // copy of a message they are in the middle of answering.
        if ($order->whatsapp_state !== null && $order->whatsapp_state !== 'failed') {
            return;
        }

        $result = $sender->sendConfirmation($link, $order);

        if (! $result['ok'] && ($result['retryable'] ?? false)) {
            $this->release($this->backoff()[$this->attempts() - 1] ?? 900);
        }
    }
}

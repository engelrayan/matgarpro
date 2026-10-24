<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Tells the customer their tap worked: "طلبك اتأكد".
 *
 * Queued so the webhook can answer Meta at once — a slow reply there makes
 * Meta deliver the same tap again.
 */
class SendWhatsappAcknowledgement implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $orderId,
        public readonly string $intent,
    ) {}

    public function handle(WhatsappSender $sender): void
    {
        $order = Order::with('store.whatsappIntegration')->find($this->orderId);
        $link = $order?->store->whatsappIntegration;

        if ($order && $link) {
            $sender->acknowledge($link, $order, $this->intent);
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\StorePixel;
use App\Services\Pixels\MetaConversionsApi;
use App\Services\Pixels\UserData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one order's Purchase event to one pixel.
 *
 * Queued, always. The customer's checkout must never wait on Meta — a slow
 * Graph API would otherwise show up as a slow thank-you page, on the single
 * screen where the merchant most needs the sale to feel finished.
 *
 * One job per pixel rather than one per order: a store may run several, and a
 * revoked token on one must not stop the others from reporting.
 */
class SendMetaPurchaseEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly int $orderId,
        public readonly int $pixelId,
    ) {}

    /** Spread retries out; Meta accepts events up to 7 days old. */
    public function backoff(): array
    {
        return config('pixels.meta.retry_backoff');
    }

    public function handle(MetaConversionsApi $api): void
    {
        $order = Order::with('items')->find($this->orderId);
        $pixel = StorePixel::find($this->pixelId);

        // Either was deleted between queueing and running — nothing to report,
        // and nothing worth failing over.
        if (! $order || ! $pixel || ! $pixel->canSendServerSide()) {
            return;
        }

        $tracking = (array) ($order->tracking ?? []);

        $event = $api->purchaseEvent(
            eventId: self::eventIdFor($order),
            eventTime: $order->created_at->timestamp,
            sourceUrl: (string) ($tracking['source_url'] ?? $order->store->canonicalUrl()),
            value: (float) $order->total,
            currency: $order->store->currency,
            userData: UserData::build(
                email: $order->customer_email,
                phone: $order->customer_phone,
                name: $order->customer_name,
                city: $order->city,
                state: $order->governorate,
                fbp: $tracking['fbp'] ?? null,
                fbc: $tracking['fbc'] ?? null,
                ip: $order->ip,
                userAgent: $order->user_agent,
            ),
            contents: $order->items->map(fn ($item) => [
                'id' => (string) ($item->product_id ?? $item->id),
                'quantity' => $item->quantity,
                'item_price' => (float) $item->unit_price,
            ])->all(),
        );

        $result = $api->send($pixel, $event);

        if ($result['ok']) {
            $pixel->forceFill(['last_event_at' => now(), 'last_error' => null])->save();

            return;
        }

        $pixel->forceFill(['last_error' => $result['error']])->save();

        // A rejected token or a malformed event will be rejected identically
        // forever; only throw when another attempt could actually succeed.
        if ($result['retryable']) {
            $this->release($this->backoff()[$this->attempts() - 1] ?? 3_600);
        }
    }

    /**
     * The deduplication key shared with the browser pixel.
     *
     * Deterministic from the order id so both sides derive the same string
     * without having to pass one to the other. If these ever diverge, Meta
     * counts every sale twice.
     */
    public static function eventIdFor(Order $order): string
    {
        return 'purchase.' . $order->store_id . '.' . $order->id;
    }
}

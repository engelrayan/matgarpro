<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\Store;
use App\Models\StoreDamanIntegration;
use App\Services\Daman\DamanClient;

/**
 * Hands confirmed orders over to Daman.
 *
 * Daman is the merchant's wakil: it already holds their contract with a carrier
 * (or two), so this sends the order and keeps the two numbers that come back —
 * Daman's own order number, and the waybill the carrier issued. From that point
 * the parcel's status arrives by webhook.
 *
 * Nothing here decides who carries the parcel or what it costs. Daman routes by
 * governorate against the merchant's own contracts, and a second opinion from
 * us would only be a second answer to a settled question.
 */
class ShipOrdersViaDaman
{
    public function __construct(private readonly DamanClient $daman) {}

    /**
     * @param  array<int,int>  $orderIds
     * @return array{sent:int, failed:int, skipped:int, errors:array<int,string>}
     */
    public function handle(Store $store, array $orderIds): array
    {
        $link = $store->damanIntegration;

        if (! $link?->canShip()) {
            return $this->summary(errors: ['الربط مع ضمان مش مفعّل.']);
        }

        // Scoped to the store rather than trusting the posted ids — a crafted
        // list would otherwise reach another merchant's orders.
        $orders = $store->orders()
            ->whereIn('id', $orderIds)
            ->orderBy('id')
            ->get();

        $shippable = $orders->filter($this->isShippable(...));

        // Everything the merchant ticked that we will not send: already handed
        // over, not confirmed yet, or gone. Counted rather than argued about —
        // selecting a whole page and pressing ship is the normal way to use it.
        $skipped = count(array_unique($orderIds)) - $shippable->count();

        [$ready, $rejected] = $shippable
            ->partition(fn (Order $order) => $this->missingField($order) === null);

        $errors = $rejected
            ->map(fn (Order $order) => "طلب #{$order->number}: " . $this->missingField($order))
            ->values()
            ->all();

        $sent = 0;

        // Split before Daman has to: it refuses an oversized batch outright,
        // which would turn a merchant's one-click morning dispatch into a
        // single error with nothing shipped.
        foreach ($ready->chunk(DamanClient::MAX_PER_REQUEST) as $chunk) {
            $batch = $chunk->values();
            $results = $this->daman->createShipments($link, $batch->map(fn (Order $order) => $this->row($store, $link, $order))->all());

            foreach ($batch as $i => $order) {
                $result = $results[$i] ?? ['ok' => false, 'error' => 'مفيش رد من ضمان على الطلب ده.'];

                if ($result['ok']) {
                    $this->recordSuccess($order, $result['shipment']);
                    $sent++;

                    continue;
                }

                $order->forceFill(['daman_error' => $result['error']])->save();
                $errors[] = "طلب #{$order->number}: {$result['error']}";
            }
        }

        $link->forceFill([
            'last_shipped_at' => $sent > 0 ? now() : $link->last_shipped_at,
            // The connection itself is fine when only some rows were rejected;
            // per-order problems belong on the order, not on the link.
            'last_error' => $sent > 0 ? null : ($errors[0] ?? $link->last_error),
        ])->save();

        return $this->summary(sent: $sent, skipped: $skipped, errors: $errors);
    }

    /**
     * The order as Daman's API wants it.
     *
     * @return array<string,mixed>
     */
    private function row(Store $store, StoreDamanIntegration $link, Order $order): array
    {
        return [
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'customer_address' => $this->address($order),
            // Daman resolves the name against its own governorate table — we
            // deliberately do not keep a mapping of our own, because two copies
            // of that list drift and the parcel is what pays for it.
            'governorate_name' => $order->governorate,
            'cod_amount' => $this->codAmount($link, $order),
            // What the merchant sees on the Daman side, so an order can be
            // matched back to this store by eye.
            'reference' => $store->slug . '#' . $order->number,
            'notes' => $order->note,
        ];
    }

    /**
     * What the courier collects at the door.
     *
     * Has to agree with the same setting on the merchant's Daman account. When
     * Daman treats collection as inclusive of shipping we send the total the
     * customer agreed to; when it adds shipping on top we send the merchandise
     * alone, and Daman's own tariff decides the rest.
     */
    private function codAmount(StoreDamanIntegration $link, Order $order): float
    {
        $total = (float) $order->total;

        return $link->cod_includes_shipping
            ? round($total, 2)
            : round($total - (float) $order->shipping_amount, 2);
    }

    /** City and street as one line — Daman prices some routes off the address. */
    private function address(Order $order): string
    {
        return collect([$order->address, $order->city, $order->governorate])
            ->filter()
            ->unique()
            ->implode(' - ');
    }

    /** @param array<string,mixed> $shipment */
    private function recordSuccess(Order $order, array $shipment): void
    {
        $order->forceFill([
            'daman_order_number' => $shipment['daman_order_number'] ?? null,
            'daman_shipment_id' => $shipment['id'] ?? null,
            'daman_tracking_number' => $shipment['tracking_number'] ?? null,
            'daman_carrier_name' => data_get($shipment, 'shipping_company.name'),
            'daman_status' => $shipment['status'] ?? null,
            'daman_sent_at' => now(),
            'daman_error' => null,
            // The parcel has left as far as the merchant is concerned; every
            // status after this one arrives from Daman by webhook.
            'status' => Order::STATUS_SHIPPED,
        ])->save();
    }

    /**
     * Orders we will not hand over, and why.
     *
     * Confirmed only, on purpose: these are cash-on-delivery parcels, and
     * dispatching one the merchant has not looked at is the expensive mistake —
     * a wrong phone number costs them the shipping twice.
     */
    private function isShippable(Order $order): bool
    {
        return $order->status === Order::STATUS_CONFIRMED
            && $order->daman_shipment_id === null;
    }

    /** The first thing Daman would reject this order for, in the merchant's words. */
    private function missingField(Order $order): ?string
    {
        return match (true) {
            blank($order->governorate) => 'مافيش محافظة.',
            mb_strlen(trim($this->address($order))) < 5 => 'العنوان ناقص.',
            mb_strlen(trim((string) $order->customer_name)) < 3 => 'اسم العميل ناقص.',
            mb_strlen(trim((string) $order->customer_phone)) < 9 => 'رقم التليفون ناقص.',
            default => null,
        };
    }

    /**
     * @param  array<int,string>  $errors
     * @return array{sent:int, failed:int, skipped:int, errors:array<int,string>}
     */
    private function summary(int $sent = 0, int $skipped = 0, array $errors = []): array
    {
        return [
            'sent' => $sent,
            'failed' => count($errors),
            'skipped' => $skipped,
            // A merchant reads the first few and acts on them; the rest is a
            // wall of text they scroll past.
            'errors' => array_slice($errors, 0, 8),
        ];
    }
}

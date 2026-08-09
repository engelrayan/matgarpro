<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StoreDamanIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Status changes pushed by Daman.
 *
 * Daman posts one URL per merchant account, so the store is named by an
 * unguessable token in the path and the body is signed with the secret Daman
 * issued. Both have to check out: the token alone would let anyone who saw a
 * URL in a log mark a merchant's parcels delivered.
 *
 * Always answers 2xx once the request is genuine, including for an order we do
 * not recognise. Daman retries a non-2xx with backoff for hours, and a shipment
 * created outside this store is not a failure worth that.
 */
class DamanWebhookController extends Controller
{
    /** Daman's own status → the status a merchant reads on the grid. */
    private const STATUS_MAP = [
        'delivered' => Order::STATUS_DELIVERED,
        'partial_delivered' => Order::STATUS_DELIVERED,
        'returned' => Order::STATUS_RETURNED,
        'refused' => Order::STATUS_RETURNED,
        'refused_paid' => Order::STATUS_RETURNED,
        'cancelled' => Order::STATUS_CANCELLED,
    ];

    public function __invoke(Request $request, string $token): JsonResponse
    {
        $link = StoreDamanIntegration::query()->where('webhook_token', $token)->first();

        if (! $link || ! filled($link->webhook_secret)) {
            // Same answer either way: a probe must not be able to tell a wrong
            // token from a store that has not pasted its secret yet.
            return response()->json(['message' => 'Unknown endpoint.'], 404);
        }

        if (! $this->signatureIsValid($request, $link->webhook_secret)) {
            Log::warning('Daman webhook: bad signature', ['store_id' => $link->store_id]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $link->forceFill(['last_webhook_at' => now()])->save();

        $shipmentId = $request->integer('shipment_id');

        $order = Order::query()
            ->where('store_id', $link->store_id)
            ->where('daman_shipment_id', $shipmentId)
            ->first();

        if (! $order) {
            return response()->json(['message' => 'No matching order.']);
        }

        $this->apply($order, $request);

        return response()->json(['message' => 'ok']);
    }

    /**
     * Move the order, if Daman's news actually moves it.
     *
     * Daman reports two things: the order's own lifecycle and the carrier's
     * view of the parcel. The second is the one that carries a refusal or a
     * return, so it wins when the two disagree — a parcel that came back is
     * back, whatever the ledger still says.
     */
    private function apply(Order $order, Request $request): void
    {
        $shippingStatus = (string) $request->input('shipping_status', '');
        $damanStatus = (string) $request->input('status', '');

        $updates = [
            'daman_status' => $shippingStatus ?: $damanStatus,
            'daman_status_note' => $request->input('shipping_status_note'),
            // Daman assigns the waybill when it dispatches to the carrier,
            // which can be after the shipment was created — so a later status
            // change is often where the tracking number actually arrives.
            'daman_tracking_number' => $request->input('tracking_number') ?: $order->daman_tracking_number,
        ];

        $mapped = self::STATUS_MAP[$shippingStatus] ?? self::STATUS_MAP[$damanStatus] ?? null;

        // Never walked backwards: a late "out for delivery" arriving after the
        // parcel was delivered must not un-deliver it, and a merchant who
        // cancelled an order by hand has made a decision we do not overrule.
        if ($mapped !== null && $order->isOpen()) {
            $updates['status'] = $mapped;
        }

        $order->forceFill($updates)->save();
    }

    /**
     * HMAC-SHA256 over the raw body, as Daman signs it.
     *
     * Compared with hash_equals rather than `===`: the timing of a plain string
     * comparison leaks how much of a guess was right, one byte at a time.
     */
    private function signatureIsValid(Request $request, string $secret): bool
    {
        $sent = (string) $request->header('X-Daman-Signature');
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return $sent !== '' && hash_equals($expected, $sent);
    }
}

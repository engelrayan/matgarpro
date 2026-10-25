<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreWhatsappIntegration;
use App\Services\Whatsapp\DriverFactory;
use App\Services\Whatsapp\InboundReplyHandler;
use App\Services\Whatsapp\ReportsSessionStatus;
use App\Services\Whatsapp\Waha\WahaClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Customer replies, pushed by whichever gateway the store uses.
 *
 * The token in the path names the store; the driver decides whether the body
 * can be trusted beyond that. Always answers 2xx once a request is genuine,
 * including for a reply we could not match — gateways retry a non-2xx, and a
 * message from a stranger is not a failure worth retrying.
 */
class WhatsappWebhookController extends Controller
{
    public function __construct(
        private readonly DriverFactory $drivers,
        private readonly InboundReplyHandler $replies,
    ) {}

    /**
     * Meta subscribes by calling the webhook with a challenge it wants echoed
     * back, in the clear, as plain text. Other gateways just POST.
     */
    public function verify(Request $request, string $token): Response
    {
        $link = $this->linkFor($token);

        if (! $link) {
            return response('Unknown endpoint.', 404);
        }

        $challenge = $this->drivers->make($link)->webhookChallenge($request);

        return $challenge !== null
            ? response($challenge, 200)->header('Content-Type', 'text/plain')
            : response('Verification failed.', 403);
    }

    public function handle(Request $request, string $token): Response
    {
        $link = $this->linkFor($token);

        if (! $link) {
            return response('Unknown endpoint.', 404);
        }

        $driver = $this->drivers->make($link);

        if (! $driver->verifyWebhookSignature($request)) {
            Log::warning('WhatsApp webhook: bad signature', ['store_id' => $link->store_id]);

            return response('Invalid signature.', 401);
        }

        /*
         | The linked number connected or dropped (WAHA). Recorded on the link,
         | which is what decides whether the store sends at all — so a phone
         | that logged the session out stops being sent to on the next order,
         | and the settings screen says why.
         */
        if ($driver instanceof ReportsSessionStatus && ($status = $driver->sessionStatus($request))) {
            $working = $status['status'] === WahaClient::WORKING;

            $link->forceFill([
                'credentials' => [...(array) $link->credentials, 'status' => $status['status']],
                'sender_phone' => $status['phone'] ?? $link->sender_phone,
                'last_error' => $working ? null : $this->statusMessage($status['status']),
            ])->save();

            return response('ok', 200);
        }

        $messages = $driver->parseWebhook($request);

        /*
         | Nothing recognisable in the body.
         |
         | Normal for Meta — delivery receipts and read receipts arrive on the
         | same webhook. Logged for the unofficial gateway, where an unfamiliar
         | payload is the difference between a reply we can read and a customer
         | whose answer vanished, and the raw body is the only way to add it.
         */
        if ($messages === []) {
            if ($link->isSessionGateway()) {
                Log::info('WhatsApp webhook: nothing parsed', [
                    'store_id' => $link->store_id,
                    'driver' => $link->driver,
                    'payload' => $request->all(),
                ]);
            }

            return response('ok', 200);
        }

        foreach ($messages as $message) {
            $this->replies->handle($link, $message);
        }

        return response('ok', 200);
    }

    /** What a WAHA status means to the merchant, in a sentence. */
    private function statusMessage(string $status): string
    {
        return match ($status) {
            WahaClient::SCAN_QR => 'الرقم اتفصل — امسح الـ QR تاني من إعدادات الواتساب.',
            WahaClient::FAILED => 'الربط وقع. افتح إعدادات الواتساب واربط الرقم تاني.',
            'STOPPED' => 'الربط واقف. افتح إعدادات الواتساب واربط الرقم تاني.',
            default => 'الرقم بيتوصّل…',
        };
    }

    private function linkFor(string $token): ?StoreWhatsappIntegration
    {
        return StoreWhatsappIntegration::query()->where('webhook_token', $token)->first();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Whatsapp\InboundReplyHandler;
use App\Services\Whatsapp\PlatformDriver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Replies to the platform's shared WhatsApp number — every store's customers,
 * on one URL.
 *
 * So the URL names nobody and the signature is the whole of the trust: an
 * unsigned body is refused outright (see PlatformDriver), and each reply finds
 * its own order in InboundReplyHandler::handlePlatform(). Always 200 once a
 * request is genuine, matched or not — Meta retries anything else.
 */
class PlatformWhatsappWebhookController extends Controller
{
    public function __construct(
        private readonly PlatformDriver $driver,
        private readonly InboundReplyHandler $replies,
    ) {}

    /** Meta's one-time subscription handshake. */
    public function verify(Request $request): Response
    {
        $challenge = $this->driver->webhookChallenge($request);

        return $challenge !== null
            ? response($challenge, 200)->header('Content-Type', 'text/plain')
            : response('Verification failed.', 403);
    }

    public function handle(Request $request): Response
    {
        if (! $this->driver->verifyWebhookSignature($request)) {
            Log::warning('Platform WhatsApp webhook: bad or missing signature');

            return response('Invalid signature.', 401);
        }

        // Delivery and read receipts arrive here too and parse to nothing.
        foreach ($this->driver->parseWebhook($request) as $message) {
            $this->replies->handlePlatform($message);
        }

        return response('ok', 200);
    }
}

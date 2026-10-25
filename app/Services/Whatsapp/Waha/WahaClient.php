<?php

namespace App\Services\Whatsapp\Waha;

use App\Models\StoreWhatsappIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The few calls the app makes to our own WAHA server.
 *
 * One WAHA session per store, named after the store, so the session in every
 * webhook says whose number it came in on and no store can ever be handed
 * another's session. Everything about the merchant's WhatsApp lives inside
 * WAHA; all we keep is the session's name and its last known status.
 */
class WahaClient
{
    /** WAHA's statuses we act on. Anything else is "not ready yet". */
    public const WORKING = 'WORKING';

    public const SCAN_QR = 'SCAN_QR_CODE';

    public const FAILED = 'FAILED';

    /** The events a store's session reports back. */
    private const EVENTS = ['message.any', 'message.ack', 'poll.vote', 'session.status'];

    /** Is there a WAHA server to use? Without one the option is not offered. */
    public static function configured(): bool
    {
        return filled(config('services.whatsapp.waha.url'))
            && filled(config('services.whatsapp.waha.api_key'))
            && filled(config('services.whatsapp.waha.webhook_secret'));
    }

    public static function sessionFor(StoreWhatsappIntegration $link): string
    {
        return 'store-' . $link->store_id;
    }

    /**
     * Create the store's session if it does not exist, point its webhook at
     * the store's URL, and start it. Safe to call again: an existing session
     * gets its config refreshed and is started if it was stopped.
     *
     * @throws WahaException
     */
    public function connect(StoreWhatsappIntegration $link): void
    {
        $name = self::sessionFor($link);
        $config = [
            'webhooks' => [[
                'url' => rtrim((string) config('services.whatsapp.waha.callback_url'), '/')
                    . "/api/integrations/whatsapp/webhook/{$link->webhook_token}",
                'events' => self::EVENTS,
                'hmac' => ['key' => (string) config('services.whatsapp.waha.webhook_secret')],
                'retries' => ['policy' => 'exponential', 'delaySeconds' => 2, 'attempts' => 6],
            ]],
            // Stored on the session and echoed in every webhook.
            'metadata' => ['store_id' => (string) $link->store_id],
        ];

        $existing = $this->call(fn (PendingRequest $http) => $http->get("/api/sessions/{$name}"), allowMissing: true);

        if ($existing === null) {
            $this->call(fn (PendingRequest $http) => $http->post('/api/sessions', [
                'name' => $name,
                'start' => true,
                'config' => $config,
            ]));

            return;
        }

        $this->call(fn (PendingRequest $http) => $http->put("/api/sessions/{$name}", ['config' => $config]));

        // A linked number is left alone. Anything else is restarted, not just
        // started: a session whose QR codes ran out sits in FAILED, and
        // "start" on it does nothing — restart is what hands out fresh codes.
        if (data_get($existing, 'status') !== self::WORKING) {
            $this->call(fn (PendingRequest $http) => $http->post("/api/sessions/{$name}/restart"));
        }
    }

    /**
     * Where the session is, and which number it is linked to once it is.
     *
     * @return array{status: string, phone: ?string, name: ?string}
     *
     * @throws WahaException
     */
    public function status(StoreWhatsappIntegration $link): array
    {
        $session = $this->call(fn (PendingRequest $http) => $http->get('/api/sessions/' . self::sessionFor($link)), allowMissing: true);

        if ($session === null) {
            return ['status' => 'STOPPED', 'phone' => null, 'name' => null];
        }

        return [
            'status' => (string) data_get($session, 'status', 'STOPPED'),
            'phone' => self::phoneFromJid(data_get($session, 'me.id')),
            'name' => data_get($session, 'me.pushName'),
        ];
    }

    /**
     * The QR code to scan, as a base64 PNG — or null when the session is not
     * waiting for a scan.
     *
     * @throws WahaException
     */
    public function qr(StoreWhatsappIntegration $link): ?string
    {
        $qr = $this->call(
            fn (PendingRequest $http) => $http->get('/api/' . self::sessionFor($link) . '/auth/qr'),
            allowMissing: true,
        );

        return is_string(data_get($qr, 'data')) ? data_get($qr, 'data') : null;
    }

    /**
     * Unlink the number and remove the session. The merchant's WhatsApp app
     * shows the linked device gone.
     */
    public function disconnect(StoreWhatsappIntegration $link): void
    {
        try {
            $this->call(fn (PendingRequest $http) => $http->delete('/api/sessions/' . self::sessionFor($link)), allowMissing: true);
        } catch (WahaException) {
            // Best effort: the merchant asked to disconnect, and our side of
            // that happens regardless of whether WAHA is reachable right now.
        }
    }

    /**
     * @return array<string,mixed> WAHA's answer, carrying the message id
     *
     * @throws WahaException
     */
    public function sendText(StoreWhatsappIntegration $link, string $phone, string $text): array
    {
        return (array) $this->call(fn (PendingRequest $http) => $http->post('/api/sendText', [
            'session' => self::sessionFor($link),
            'chatId' => $phone . '@c.us',
            'text' => $text,
        ]));
    }

    /**
     * A poll — what stands in for buttons on an unofficial number, where
     * WhatsApp does not deliver buttons reliably and does deliver polls.
     *
     * @param  array<int,string>  $options
     * @return array<string,mixed>
     *
     * @throws WahaException
     */
    public function sendPoll(StoreWhatsappIntegration $link, string $phone, string $question, array $options): array
    {
        return (array) $this->call(fn (PendingRequest $http) => $http->post('/api/sendPoll', [
            'session' => self::sessionFor($link),
            'chatId' => $phone . '@c.us',
            'poll' => ['name' => $question, 'options' => $options, 'multipleAnswers' => false],
        ]));
    }

    /** `201001234567@c.us` → `201001234567`; groups and broadcasts → null. */
    public static function phoneFromJid(mixed $jid): ?string
    {
        if (! is_string($jid) || ! preg_match('/^([^@]+)@(c\.us|s\.whatsapp\.net)$/', $jid, $m)) {
            return null;
        }

        // GOWS can append a device suffix: 201001234567:12@c.us.
        $digits = preg_replace('/\D/', '', strtok($m[1], ':') ?: '');

        return $digits !== '' ? $digits : null;
    }

    /**
     * The phone number behind a hidden id (`95589446484048@lid`) — WhatsApp
     * increasingly sends those instead of the number. Null when WAHA does
     * not know it.
     */
    public function phoneForLid(StoreWhatsappIntegration $link, string $lid): ?string
    {
        try {
            $found = (array) $this->call(fn (PendingRequest $http) => $http->get(
                '/api/' . self::sessionFor($link) . '/lids/' . rawurlencode($lid),
            ));
        } catch (WahaException) {
            return null;
        }

        return self::phoneFromJid($found['pn'] ?? null);
    }

    /** A message id from any of the shapes the engines answer with. */
    public static function messageId(array $response): ?string
    {
        $id = data_get($response, 'id._serialized') ?? data_get($response, 'id.id') ?? data_get($response, 'key.id') ?? data_get($response, 'id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     * @return array<string,mixed>|null null for a 404 when `allowMissing`
     *
     * @throws WahaException
     */
    private function call(callable $request, bool $allowMissing = false): ?array
    {
        try {
            $response = $request(
                Http::baseUrl(rtrim((string) config('services.whatsapp.waha.url'), '/'))
                    ->withHeaders(['X-Api-Key' => (string) config('services.whatsapp.waha.api_key')])
                    ->acceptJson()
                    ->timeout(20),
            );
        } catch (ConnectionException) {
            throw new WahaException('مقدرناش نوصل لسيرفر الواتساب دلوقتي. جرّب تاني بعد شوية.', retryable: true);
        }

        if ($allowMissing && $response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new WahaException(
                'سيرفر الواتساب رفض الطلب (' . $response->status() . ').',
                retryable: $response->serverError(),
            );
        }

        return (array) $response->json();
    }
}

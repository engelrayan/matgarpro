<?php

namespace App\Services\Whatsapp;

use App\Models\StoreWhatsappIntegration;
use App\Services\Whatsapp\Concerns\ParsesLooseWebhooks;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Whats360 (whats360.live) — an Arabic-market gateway driving a WhatsApp
 * session linked by QR, like Wapilot rather than like Meta.
 *
 * Its published contract is one endpoint:
 *
 *   POST {base}/api/v1/send
 *   { token, instance_id, jid: "201012345678@s.whatsapp.net", message }
 *   → { success: true, message_id: "3EB0C4…" }
 *
 * Same trade-off as any unofficial gateway: no templates and no approval queue,
 * against a number WhatsApp can ban without warning. Said plainly on the
 * settings screen so the merchant is choosing it, not stumbling into it.
 */
class Whats360Driver implements WhatsappDriver
{
    use ParsesLooseWebhooks;

    /**
     * Their marketing page documents the path but not the host, and their own
     * writing elsewhere uses a different one — so the base is a field the
     * merchant can correct rather than a constant we would be guessing at.
     */
    public const DEFAULT_BASE = 'https://whats360.live';

    public function __construct(private readonly StoreWhatsappIntegration $link) {}

    public function send(string $phone, string $body, array $variables = []): SendResult
    {
        try {
            $response = Http::acceptJson()->timeout(20)->post($this->url(), [
                'token' => (string) $this->link->credential('token'),
                'instance_id' => (string) $this->link->credential('instance_id'),
                'jid' => $this->jid($phone),
                'message' => $body,
            ]);
        } catch (ConnectionException) {
            return SendResult::failed('مقدرناش نوصل لـ Whats360 دلوقتي.', retryable: true);
        }

        if ($this->tokenWasRejected($response->status(), $response->json())) {
            return SendResult::failed('Whats360 رفض التوكن أو الـ instance. راجعهم من لوحة التحكم بتاعتهم.');
        }

        if (! $response->successful()) {
            return SendResult::failed(
                $this->errorFrom($response->json(), $response->status()),
                retryable: $response->serverError(),
            );
        }

        /*
         | A 200 that says `success: false`.
         |
         | Session gateways answer this way when the phone has gone offline or
         | the device was unlinked — the HTTP call worked, the message did not.
         | Reading only the status code here would mark the order `sent` and
         | leave the merchant waiting for a reply to a message nobody received.
         */
        $json = $response->json();

        if (array_key_exists('success', (array) $json) && ! $json['success']) {
            return SendResult::failed($this->errorFrom($json, 200));
        }

        $id = data_get($json, 'message_id') ?? data_get($json, 'data.message_id');

        return SendResult::sent(is_scalar($id) ? (string) $id : null);
    }

    /**
     * Check the keys without messaging anybody.
     *
     * Whats360 publishes no status endpoint, so this posts to the send endpoint
     * with an empty recipient: a well-formed request that cannot deliver
     * anything. A rejected token answers 401/403 (or says so in the body) and
     * we stop; a complaint about the missing `jid` means the credentials got
     * past the door, which is what we came to find out.
     *
     * Deliberately not a real send. Verifying by messaging the merchant's own
     * number would work once and be a nuisance every time after.
     */
    public function verify(): array
    {
        try {
            $response = Http::acceptJson()->timeout(20)->post($this->url(), [
                'token' => (string) $this->link->credential('token'),
                'instance_id' => (string) $this->link->credential('instance_id'),
                'jid' => '',
                'message' => '',
            ]);
        } catch (ConnectionException) {
            return ['ok' => false, 'message' => 'مقدرناش نوصل لـ Whats360. اتأكد إن الرابط مظبوط وجرّب تاني.'];
        }

        if ($response->status() === 404) {
            return ['ok' => false, 'message' => 'الرابط ده مالوش endpoint للإرسال. راجع «رابط الخدمة» في الإعدادات.'];
        }

        if ($this->tokenWasRejected($response->status(), $response->json())) {
            return ['ok' => false, 'message' => 'التوكن أو الـ instance مرفوض من Whats360.'];
        }

        if ($response->serverError()) {
            return ['ok' => false, 'message' => 'Whats360 رد بخطأ (' . $response->status() . '). جرّب تاني بعد شوية.'];
        }

        return [
            'ok' => true,
            // Honest about what was and was not proven: the keys were accepted,
            // but only a real message proves the phone is still linked.
            'message' => 'المفاتيح مقبولة من Whats360. ابعت رسالة تجربة عشان تتأكد إن الجهاز متوصّل.',
        ];
    }

    /** Whats360 posts deliveries straight to the URL; there is no handshake. */
    public function webhookChallenge(Request $request): ?string
    {
        return null;
    }

    /**
     * Whats360 does not sign its webhooks, so the token in the URL is the only
     * secret. Accepted knowingly — the settings screen says so, and the reply
     * reader will only ever move an order that is already waiting on one.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        return true;
    }

    // ── private ─────────────────────────────────────────────────────────────

    private function jid(string $phone): string
    {
        return $phone . '@s.whatsapp.net';
    }

    private function url(): string
    {
        $base = $this->link->credential('base_url') ?: self::DEFAULT_BASE;

        return rtrim($base, '/') . '/api/v1/send';
    }

    /**
     * Gateways disagree about whether a bad token is a 401 or a 200 saying so
     * in Arabic, so both are checked.
     */
    private function tokenWasRejected(int $status, mixed $json): bool
    {
        if (in_array($status, [401, 403], true)) {
            return true;
        }

        $message = mb_strtolower((string) (data_get($json, 'message') ?? data_get($json, 'error') ?? ''));

        return $message !== '' && (
            str_contains($message, 'token')
            || str_contains($message, 'unauthor')
            || str_contains($message, 'instance')
            || str_contains($message, 'توكن')
        );
    }

    private function errorFrom(mixed $json, int $status): string
    {
        $message = data_get($json, 'message') ?? data_get($json, 'error');

        return is_string($message) && $message !== ''
            ? $message
            : 'Whats360 رفض الرسالة (' . $status . ').';
    }
}

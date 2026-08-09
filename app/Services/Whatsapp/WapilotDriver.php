<?php

namespace App\Services\Whatsapp;

use App\Models\StoreWhatsappIntegration;
use App\Support\Phone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Wapilot — an unofficial gateway driving a real WhatsApp Web session.
 *
 * No templates and no approval queue: whatever the merchant writes is what the
 * customer reads, immediately. The price is that WhatsApp does not know about
 * it, so a number sending hundreds of unanswered messages a day can be banned
 * with no warning and no appeal. Said plainly on the settings screen.
 */
class WapilotDriver implements WhatsappDriver
{
    private const BASE = 'https://api.wapilot.net/api/v2';

    public function __construct(private readonly StoreWhatsappIntegration $link) {}

    public function send(string $phone, string $body, array $variables = []): SendResult
    {
        try {
            $response = $this->request()->post($this->url('send-message'), [
                'chat_id' => $this->chatId($phone),
                'text' => $body,
            ]);
        } catch (ConnectionException) {
            return SendResult::failed('مقدرناش نوصل لواتساب دلوقتي.', retryable: true);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return SendResult::failed('الجهاز مرفوض — راجع التوكن والـ instance في إعدادات الواتساب.');
        }

        if (! $response->successful()) {
            return SendResult::failed(
                $this->errorFrom($response->json(), $response->status()),
                // 5xx is the gateway having a bad minute; 4xx is our request.
                retryable: $response->serverError(),
            );
        }

        return SendResult::sent($this->messageIdFrom($response->json()));
    }

    public function verify(): array
    {
        try {
            $response = $this->request()->get($this->url('status'));
        } catch (ConnectionException) {
            return ['ok' => false, 'message' => 'مقدرناش نوصل لـ Wapilot دلوقتي. جرّب تاني بعد شوية.'];
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return ['ok' => false, 'message' => 'التوكن أو الـ instance مرفوض. راجعهم من لوحة Wapilot.'];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'Wapilot رد بخطأ (' . $response->status() . ').'];
        }

        return ['ok' => true, 'message' => 'الاتصال بواتساب تمام والجهاز متوصّل.'];
    }

    /** Wapilot posts deliveries straight to the URL; there is no handshake. */
    public function webhookChallenge(Request $request): ?string
    {
        return null;
    }

    /**
     * Pull replies out of whatever shape arrived.
     *
     * Deliberately tolerant. Unofficial gateways change their payload between
     * versions and document it thinly, so this reads the shapes seen in the
     * wild rather than one it insists on — and the raw body is logged either
     * way, so an unrecognised shape is something we can see and add, not a
     * silent loss of a customer's answer.
     *
     * @return array<int,InboundMessage>
     */
    public function parseWebhook(Request $request): array
    {
        $payload = $request->all();

        $rows = data_get($payload, 'data.messages')
            ?? data_get($payload, 'messages')
            ?? data_get($payload, 'data')
            ?? [$payload];

        if (! is_array($rows)) {
            return [];
        }

        // A single message object rather than a list of them.
        if (! array_is_list($rows)) {
            $rows = [$rows];
        }

        $messages = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            // Our own outgoing messages come back on the same webhook; echoing
            // them into the reply reader would confirm orders nobody answered.
            if (filter_var(data_get($row, 'from_me') ?? data_get($row, 'fromMe'), FILTER_VALIDATE_BOOL)) {
                continue;
            }

            $from = (string) (data_get($row, 'from') ?? data_get($row, 'chat_id') ?? data_get($row, 'author') ?? '');
            $body = data_get($row, 'body') ?? data_get($row, 'text') ?? data_get($row, 'message') ?? '';

            if ($from === '' || ! is_string($body) || trim($body) === '') {
                continue;
            }

            $messages[] = new InboundMessage(
                // `201006262330@c.us` → the digits are all we match on.
                phone: Phone::e164(explode('@', $from)[0]),
                body: trim($body),
                providerMessageId: (string) (data_get($row, 'id') ?? data_get($row, 'message_id') ?? '') ?: null,
            );
        }

        return $messages;
    }

    /**
     * Wapilot does not sign its webhooks, so the token in the URL is the only
     * secret. Accepted knowingly — the settings screen says so, and the reply
     * reader will only ever move an order that is already waiting on one.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        return true;
    }

    private function chatId(string $phone): string
    {
        return $phone . '@c.us';
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders(['token' => (string) $this->link->credential('token')])
            ->acceptJson()
            ->timeout(20);
    }

    private function url(string $path): string
    {
        return self::BASE . '/' . trim((string) $this->link->credential('instance'), '/') . '/' . $path;
    }

    private function errorFrom(mixed $json, int $status): string
    {
        $message = data_get($json, 'message') ?? data_get($json, 'error');

        return is_string($message) && $message !== ''
            ? $message
            : 'واتساب رفض الرسالة (' . $status . ').';
    }

    private function messageIdFrom(mixed $json): ?string
    {
        $id = data_get($json, 'data.id') ?? data_get($json, 'id') ?? data_get($json, 'message_id');

        return is_scalar($id) ? (string) $id : null;
    }
}

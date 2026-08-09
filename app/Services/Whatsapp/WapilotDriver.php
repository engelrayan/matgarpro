<?php

namespace App\Services\Whatsapp;

use App\Models\StoreWhatsappIntegration;
use App\Services\Whatsapp\Concerns\ParsesLooseWebhooks;
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
    use ParsesLooseWebhooks;

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

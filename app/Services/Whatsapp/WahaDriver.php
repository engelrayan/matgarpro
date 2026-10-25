<?php

namespace App\Services\Whatsapp;

use App\Models\Order;
use App\Models\StoreWhatsappIntegration;
use App\Services\Whatsapp\Waha\WahaClient;
use App\Services\Whatsapp\Waha\WahaException;
use Illuminate\Http\Request;

/**
 * The store's own number, through our self-hosted WAHA gateway.
 *
 * Linked by scanning a QR code in the dashboard — no gateway account, no
 * keys. The confirmation goes out as the merchant's message followed by a
 * poll with «تأكيد الطلب» and «إلغاء الطلب»: a tap on either comes back as a
 * vote, which is read exactly like the words in it would be.
 *
 * Unofficial, like every session gateway: WhatsApp can ban a number that
 * looks automated. So it sends only what an order makes necessary, and the
 * settings screen says so before the merchant links anything.
 */
class WahaDriver implements ConfirmsWithButtons, ReportsSessionStatus, WhatsappDriver
{
    public const CONFIRM_OPTION = '✅ تأكيد الطلب';

    public const CANCEL_OPTION = '❌ إلغاء الطلب';

    public function __construct(
        private readonly StoreWhatsappIntegration $link,
        private readonly WahaClient $client,
    ) {}

    public function send(string $phone, string $body, array $variables = []): SendResult
    {
        return $this->attempt(fn () => $this->client->sendText($this->link, $phone, $body));
    }

    /**
     * The message, then the poll under it. The poll's id is the one kept:
     * a vote names the poll it belongs to.
     */
    public function sendOrderConfirmation(string $phone, string $body, array $variables, Order $order): SendResult
    {
        $text = $this->send($phone, $body);

        if (! $text->ok) {
            return $text;
        }

        return $this->attempt(fn () => $this->client->sendPoll(
            $this->link,
            $phone,
            "تأكّد طلبك رقم #{$order->number}؟",
            [self::CONFIRM_OPTION, self::CANCEL_OPTION],
        ));
    }

    public function sendText(string $phone, string $body): SendResult
    {
        return $this->send($phone, $body);
    }

    public function verify(): array
    {
        try {
            $status = $this->client->status($this->link);
        } catch (WahaException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return $status['status'] === WahaClient::WORKING
            ? ['ok' => true, 'message' => 'الرقم متوصّل — ' . ($status['phone'] ?? ''), 'sender_phone' => $status['phone']]
            : ['ok' => false, 'message' => 'الرقم لسه مش متوصّل. امسح الـ QR من الموبايل.'];
    }

    public function webhookChallenge(Request $request): ?string
    {
        return null;
    }

    /**
     * Typed replies and poll votes, as one kind of reply. Our own messages,
     * groups and status updates are not replies and are skipped.
     *
     * @return array<int,InboundMessage>
     */
    public function parseWebhook(Request $request): array
    {
        $payload = (array) $request->input('payload', []);

        return match ($request->input('event')) {
            'message' => $this->typed($payload),
            'poll.vote' => $this->vote($payload),
            default => [],
        };
    }

    /**
     * HMAC-SHA512 over the raw body, with the secret every session was
     * created with. Required — there is no unsigned mode.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = (string) config('services.whatsapp.waha.webhook_secret');
        $sent = strtolower((string) $request->header('X-Webhook-Hmac'));

        return $secret !== '' && $sent !== ''
            && hash_equals(hash_hmac('sha512', $request->getContent(), $secret), $sent);
    }

    public function sessionStatus(Request $request): ?array
    {
        if ($request->input('event') !== 'session.status') {
            return null;
        }

        return [
            'status' => (string) $request->input('payload.status'),
            'phone' => WahaClient::phoneFromJid($request->input('me.id')),
        ];
    }

    /** @return array<int,InboundMessage> */
    private function typed(array $payload): array
    {
        $phone = WahaClient::phoneFromJid($payload['from'] ?? null);
        $body = trim((string) ($payload['body'] ?? ''));

        if (($payload['fromMe'] ?? false) || $phone === null || $body === '') {
            return [];
        }

        return [new InboundMessage(phone: $phone, body: $body, providerMessageId: $payload['id'] ?? null)];
    }

    /** @return array<int,InboundMessage> */
    private function vote(array $payload): array
    {
        $phone = WahaClient::phoneFromJid(data_get($payload, 'vote.from'));
        $choice = trim((string) (data_get($payload, 'vote.selectedOptions.0') ?? ''));

        // An emptied vote — the customer took their choice back — says nothing.
        if (data_get($payload, 'vote.fromMe') || $phone === null || $choice === '') {
            return [];
        }

        return [new InboundMessage(
            phone: $phone,
            body: $choice,
            providerMessageId: data_get($payload, 'vote.id'),
            fromButton: true,
            replyTo: data_get($payload, 'poll.id'),
        )];
    }

    /** @param callable(): array<string,mixed> $call */
    private function attempt(callable $call): SendResult
    {
        try {
            return SendResult::sent(WahaClient::messageId($call()));
        } catch (WahaException $e) {
            return SendResult::failed($e->getMessage(), retryable: $e->retryable);
        }
    }
}

<?php

namespace App\Services\Whatsapp;

use App\Models\Order;
use App\Models\StoreWhatsappIntegration;
use App\Models\WhatsappMessage;
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
class WahaDriver implements ConfirmsWithButtons, ReportsDeliveries, ReportsSessionStatus, WhatsappDriver
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
     * One message: the poll itself, its title carrying the order. Two (text,
     * then a poll asking about it) read as a bot talking twice, and the
     * customer has to scroll between the details and the buttons.
     */
    public function sendOrderConfirmation(string $phone, string $body, array $variables, Order $order): SendResult
    {
        return $this->attempt(fn () => $this->client->sendPoll(
            $this->link,
            $phone,
            self::pollTitle($body),
            [self::CONFIRM_OPTION, self::CANCEL_OPTION],
        ));
    }

    /** WhatsApp refuses a poll title longer than this. */
    public const POLL_TITLE_LIMIT = 255;

    /**
     * The message as a poll title: within WhatsApp's limit, cut at the last
     * whole word with «…» rather than mid-word or by WhatsApp refusing it.
     */
    public static function pollTitle(string $body): string
    {
        $body = trim($body);

        if (mb_strlen($body) <= self::POLL_TITLE_LIMIT) {
            return $body;
        }

        $cut = mb_substr($body, 0, self::POLL_TITLE_LIMIT - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space > 200 ? mb_substr($cut, 0, $space) : $cut) . '…';
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
     * Typed messages and poll votes. `message.any` also carries what the
     * merchant writes on the linked phone, which comes back marked fromMe
     * for the inbox; groups and status updates are skipped.
     *
     * @return array<int,InboundMessage>
     */
    public function parseWebhook(Request $request): array
    {
        $payload = (array) $request->input('payload', []);

        return match ($request->input('event')) {
            'message' => ($payload['fromMe'] ?? false) ? [] : $this->typed($payload),
            'message.any' => $this->typed($payload),
            'poll.vote' => $this->vote($payload),
            default => [],
        };
    }

    /**
     * Delivered and read ticks for a message we sent.
     *
     * @return array{key: string, status: string}|null
     */
    public function deliveryUpdate(Request $request): ?array
    {
        if ($request->input('event') !== 'message.ack') {
            return null;
        }

        $status = match ((int) $request->input('payload.ack')) {
            2 => 'delivered',
            3, 4 => 'read',
            default => null,
        };
        $key = WhatsappMessage::providerKey($request->input('payload.id'));

        return $status && $key ? ['key' => $key, 'status' => $status] : null;
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

    /**
     * Who wrote, as a phone number. WhatsApp often hides it behind a `@lid`
     * id; the number then rides along in `SenderAlt`, and failing that WAHA
     * can look it up. Without this a vote from such a customer matched no
     * order and confirmed nothing.
     */
    private function sender(array $payload, ?string $from): ?string
    {
        if ($phone = WahaClient::phoneFromJid($from)) {
            return $phone;
        }

        if (! is_string($from) || ! str_ends_with($from, '@lid')) {
            return null;
        }

        return WahaClient::phoneFromJid(data_get($payload, '_data.Info.SenderAlt'))
            ?? $this->client->phoneForLid($this->link, $from);
    }

    /**
     * The customer a message the store wrote went to — the same hiding
     * applies, with `RecipientAlt` in place of `SenderAlt`.
     */
    private function recipient(array $payload): ?string
    {
        $to = $payload['to'] ?? data_get($payload, '_data.Info.Chat');

        if ($phone = WahaClient::phoneFromJid($to)) {
            return $phone;
        }

        if (! is_string($to) || ! str_ends_with($to, '@lid')) {
            return null;
        }

        return WahaClient::phoneFromJid(data_get($payload, '_data.Info.RecipientAlt'))
            ?? $this->client->phoneForLid($this->link, $to);
    }

    /** @return array<int,InboundMessage> */
    private function typed(array $payload): array
    {
        $fromMe = (bool) ($payload['fromMe'] ?? false);
        $phone = $fromMe ? $this->recipient($payload) : $this->sender($payload, $payload['from'] ?? null);
        $body = trim((string) ($payload['body'] ?? ''));
        $media = ($payload['hasMedia'] ?? false) ? $this->mediaLabel($payload) : null;

        if ($media !== null) {
            $body = trim($media . ($body !== '' ? "\n" . $body : ''));
        }

        if ($phone === null || $body === '') {
            return [];
        }

        return [new InboundMessage(
            phone: $phone,
            body: $body,
            providerMessageId: $payload['id'] ?? null,
            pushName: $fromMe ? null : (data_get($payload, '_data.Info.PushName') ?: null),
            kind: $media !== null ? WhatsappMessage::KIND_MEDIA : WhatsappMessage::KIND_TEXT,
            fromMe: $fromMe,
        )];
    }

    /**
     * What arrived, in words — the file itself stays on the phone for now.
     * The caption, if any, follows on the next line.
     */
    private function mediaLabel(array $payload): string
    {
        $type = (string) (data_get($payload, '_data.Info.MediaType') ?: data_get($payload, 'media.mimetype') ?: '');

        return match (true) {
            str_contains($type, 'ptt'), str_contains($type, 'audio') => '🎤 رسالة صوتية',
            str_contains($type, 'image') => '📷 صورة',
            str_contains($type, 'video') => '🎬 فيديو',
            str_contains($type, 'sticker') => '🙂 ملصق',
            default => '📎 ملف',
        };
    }

    /** @return array<int,InboundMessage> */
    private function vote(array $payload): array
    {
        $phone = $this->sender($payload, data_get($payload, 'vote.from'));
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

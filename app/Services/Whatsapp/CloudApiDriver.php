<?php

namespace App\Services\Whatsapp;

use App\Models\StoreWhatsappIntegration;
use App\Support\Phone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Meta's own WhatsApp Cloud API.
 *
 * Official, so the number is safe — at the cost of one rule that shapes this
 * whole driver: a conversation the business starts must be an approved
 * template. Free text is only allowed inside the 24 hours after the customer
 * writes to us, and an order confirmation is by definition the other way round.
 *
 * So: a template when one is configured, free text otherwise. The second path
 * exists because it is what a merchant's first test message looks like — they
 * write to their own number, then send from here and it works — and because
 * a template that has not been approved yet should not mean nothing sends at
 * all. The settings screen is explicit about which one is in force.
 *
 * Going direct to Meta rather than through a BSP such as 360dialog: it is the
 * same API, and the BSP's fee buys a dashboard we would not use.
 */
class CloudApiDriver implements WhatsappDriver
{
    private const GRAPH = 'https://graph.facebook.com';

    public function __construct(private readonly StoreWhatsappIntegration $link) {}

    public function send(string $phone, string $body, array $variables = []): SendResult
    {
        try {
            $response = $this->request()->post($this->url('messages'), $this->payload($phone, $body, $variables));
        } catch (ConnectionException) {
            return SendResult::failed('مقدرناش نوصل لواتساب دلوقتي.', retryable: true);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return SendResult::failed('ميتا رفضت التوكن. اعمل توكن جديد من إعدادات التطبيق وحطه هنا.');
        }

        if (! $response->successful()) {
            return SendResult::failed($this->errorFrom($response->json(), $response->status()), retryable: $response->serverError());
        }

        return SendResult::sent(data_get($response->json(), 'messages.0.id'));
    }

    public function verify(): array
    {
        try {
            // Reading the number back proves three things at once: the token is
            // live, it can see this phone number id, and the id is real.
            $response = $this->request()->get(self::GRAPH . '/' . $this->version() . '/' . $this->phoneNumberId(), [
                'fields' => 'display_phone_number,verified_name,quality_rating',
            ]);
        } catch (ConnectionException) {
            return ['ok' => false, 'message' => 'مقدرناش نوصل لميتا دلوقتي. جرّب تاني بعد شوية.'];
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return ['ok' => false, 'message' => 'التوكن مرفوض من ميتا، أو مالوش صلاحية على الرقم ده.'];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => $this->errorFrom($response->json(), $response->status())];
        }

        $number = data_get($response->json(), 'display_phone_number');
        $name = data_get($response->json(), 'verified_name');

        return [
            'ok' => true,
            'message' => trim("الاتصال تمام — الرقم {$number} " . ($name ? "({$name})" : '')),
            'sender_phone' => is_string($number) ? $number : null,
        ];
    }

    /**
     * Meta's subscription handshake: it calls the webhook once with a token we
     * chose and expects the challenge echoed back verbatim.
     */
    public function webhookChallenge(Request $request): ?string
    {
        if ($request->query('hub_mode') !== 'subscribe' && $request->query('hub.mode') !== 'subscribe') {
            return null;
        }

        $sent = (string) ($request->query('hub_verify_token') ?? $request->query('hub.verify_token') ?? '');

        if (! hash_equals((string) $this->link->verify_token, $sent)) {
            return null;
        }

        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        return is_scalar($challenge) ? (string) $challenge : null;
    }

    /** @return array<int,InboundMessage> */
    public function parseWebhook(Request $request): array
    {
        $messages = [];

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                foreach ((array) data_get($change, 'value.messages', []) as $message) {
                    $parsed = $this->parseOne($message);

                    if ($parsed) {
                        $messages[] = $parsed;
                    }
                }
            }
        }

        return $messages;
    }

    /**
     * Meta signs the raw body with the app secret.
     *
     * Optional here only because a merchant can be up and running before they
     * dig the secret out of the app dashboard — but until they do, the URL
     * token is the whole of the security, and the settings screen says so.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = $this->link->credential('app_secret');

        if (! $secret) {
            return true;
        }

        $sent = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return $sent !== '' && hash_equals($expected, $sent);
    }

    // ── private ─────────────────────────────────────────────────────────────

    private function parseOne(array $message): ?InboundMessage
    {
        $from = (string) data_get($message, 'from');

        if ($from === '') {
            return null;
        }

        /*
         | A tapped quick-reply button and a typed word arrive as different
         | types. The button's *title* is what the customer saw, so that is
         | what the reply reader is given — it reads «أكّد الطلب» the same way
         | it reads somebody typing it.
         */
        $body = match (data_get($message, 'type')) {
            'text' => data_get($message, 'text.body'),
            'button' => data_get($message, 'button.text'),
            'interactive' => data_get($message, 'interactive.button_reply.title')
                ?? data_get($message, 'interactive.list_reply.title'),
            default => null,
        };

        if (! is_string($body) || trim($body) === '') {
            return null;
        }

        return new InboundMessage(
            phone: Phone::e164($from),
            body: trim($body),
            providerMessageId: data_get($message, 'id'),
            fromButton: in_array(data_get($message, 'type'), ['button', 'interactive'], true),
        );
    }

    /** @param array<int,string> $variables */
    private function payload(string $phone, string $body, array $variables): array
    {
        $base = ['messaging_product' => 'whatsapp', 'to' => $phone];

        if (blank($this->link->template_name)) {
            return $base + ['type' => 'text', 'text' => ['body' => $body]];
        }

        return $base + [
            'type' => 'template',
            'template' => [
                'name' => $this->link->template_name,
                'language' => ['code' => $this->link->template_language ?: 'ar'],
                'components' => [[
                    'type' => 'body',
                    'parameters' => array_map(
                        fn (string $value) => ['type' => 'text', 'text' => $value],
                        array_values($variables),
                    ),
                ]],
            ],
        ];
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken((string) $this->link->credential('access_token'))
            ->acceptJson()
            ->timeout(20);
    }

    private function url(string $path): string
    {
        return self::GRAPH . '/' . $this->version() . '/' . $this->phoneNumberId() . '/' . $path;
    }

    private function phoneNumberId(): string
    {
        return (string) $this->link->credential('phone_number_id');
    }

    private function version(): string
    {
        return (string) config('services.whatsapp.cloud_api_version');
    }

    /**
     * Meta's errors are the useful kind — they name the template that is not
     * approved, or the window that has closed. Passed through rather than
     * flattened into "failed", because that sentence is the whole diagnosis.
     */
    private function errorFrom(mixed $json, int $status): string
    {
        $message = data_get($json, 'error.error_user_msg')
            ?? data_get($json, 'error.message');

        return is_string($message) && $message !== ''
            ? $message
            : 'ميتا ردت بخطأ (' . $status . ').';
    }
}

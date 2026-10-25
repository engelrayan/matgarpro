<?php

namespace App\Services\Whatsapp;

use App\Models\Order;
use App\Models\StoreWhatsappIntegration;
use App\Models\WhatsappMessage;
use Illuminate\Http\Request;

/**
 * Sending through the platform's own official number.
 *
 * The same Cloud API as a merchant's own line, so the HTTP, parsing and
 * signature work is CloudApiDriver's — given the platform's keys instead of
 * the store's. What is different is that every message is the one approved
 * template, with buttons that carry the order back to us.
 */
class PlatformDriver implements ConfirmsWithButtons, WhatsappDriver
{
    private readonly CloudApiDriver $cloud;

    public function __construct()
    {
        $this->cloud = new CloudApiDriver(new StoreWhatsappIntegration([
            'driver' => StoreWhatsappIntegration::DRIVER_CLOUD_API,
            'credentials' => [
                'access_token' => (string) config('services.whatsapp.platform.access_token'),
                'phone_number_id' => (string) config('services.whatsapp.platform.phone_number_id'),
                'app_secret' => (string) config('services.whatsapp.platform.app_secret'),
            ],
            'verify_token' => (string) config('services.whatsapp.platform.verify_token'),
        ]));
    }

    /**
     * The template without order buttons — the merchant's test message, where
     * there is no order for a tap to act on.
     */
    public function send(string $phone, string $body, array $variables = []): SendResult
    {
        return $this->cloud->sendTemplate($phone, PlatformWhatsapp::template(), PlatformWhatsapp::language(), [
            $this->bodyComponent($variables),
        ]);
    }

    public function sendOrderConfirmation(string $phone, string $body, array $variables, Order $order): SendResult
    {
        return $this->cloud->sendTemplate($phone, PlatformWhatsapp::template(), PlatformWhatsapp::language(), [
            $this->bodyComponent($variables),
            // Index 0 and 1 are the template's two quick replies, in the order
            // `PlatformWhatsapp::templateDefinition()` creates them.
            $this->button(0, ButtonPayload::for($order, WhatsappMessage::INTENT_CONFIRM)),
            $this->button(1, ButtonPayload::for($order, WhatsappMessage::INTENT_CANCEL)),
        ]);
    }

    public function sendText(string $phone, string $body): SendResult
    {
        return $this->cloud->sendText($phone, $body);
    }

    public function verify(): array
    {
        return $this->cloud->verify();
    }

    public function webhookChallenge(Request $request): ?string
    {
        // An unset verify token must never match an empty one in the request.
        return filled(config('services.whatsapp.platform.verify_token'))
            ? $this->cloud->webhookChallenge($request)
            : null;
    }

    public function parseWebhook(Request $request): array
    {
        return $this->cloud->parseWebhook($request);
    }

    /**
     * Required, unlike on a merchant's own line: one webhook carries every
     * store's customers, so an unsigned body could confirm or cancel anybody's
     * orders. No secret configured means nothing is accepted.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        return filled(config('services.whatsapp.platform.app_secret'))
            && $this->cloud->verifyWebhookSignature($request);
    }

    /** @param array<int,string> $variables */
    private function bodyComponent(array $variables): array
    {
        return [
            'type' => 'body',
            'parameters' => array_map(fn (string $value) => ['type' => 'text', 'text' => $value], array_values($variables)),
        ];
    }

    private function button(int $index, string $payload): array
    {
        return [
            'type' => 'button',
            'sub_type' => 'quick_reply',
            'index' => (string) $index,
            'parameters' => [['type' => 'payload', 'payload' => $payload]],
        ];
    }
}

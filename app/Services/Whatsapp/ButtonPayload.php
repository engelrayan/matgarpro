<?php

namespace App\Services\Whatsapp;

use App\Models\Order;
use App\Models\WhatsappMessage;

/**
 * What a confirm / cancel button carries back to us.
 *
 * `confirm:1234:3f9a…` — the decision, the order, and a signature over both.
 * The order id is what lets a tap on the platform's shared number reach the
 * right shop without guessing from the phone, and the signature means a
 * payload is only ever one we wrote: nothing else can confirm order 1234.
 */
class ButtonPayload
{
    public const INTENTS = [WhatsappMessage::INTENT_CONFIRM, WhatsappMessage::INTENT_CANCEL];

    public static function for(Order $order, string $intent): string
    {
        return $intent . ':' . $order->id . ':' . self::signature($intent, $order->id);
    }

    /** @return array{intent: string, order_id: int}|null */
    public static function read(?string $payload): ?array
    {
        $parts = explode(':', (string) $payload);

        if (count($parts) !== 3 || ! in_array($parts[0], self::INTENTS, true) || ! ctype_digit($parts[1])) {
            return null;
        }

        [$intent, $orderId, $signature] = $parts;

        if (! hash_equals(self::signature($intent, (int) $orderId), $signature)) {
            return null;
        }

        return ['intent' => $intent, 'order_id' => (int) $orderId];
    }

    /** Short: Meta caps a payload at 256 characters and this rides on every button. */
    private static function signature(string $intent, int $orderId): string
    {
        return substr(hash_hmac('sha256', "whatsapp-button|{$intent}|{$orderId}", (string) config('app.key')), 0, 20);
    }
}

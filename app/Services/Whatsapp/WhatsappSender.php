<?php

namespace App\Services\Whatsapp;

use App\Models\Order;
use App\Models\StoreWhatsappIntegration;
use App\Models\WhatsappMessage;
use App\Support\Phone;

/**
 * Sends one order's confirmation and writes down what happened.
 *
 * The only place an outbound message is created, so there is exactly one answer
 * to "did we message this customer, and what did the gateway say".
 */
class WhatsappSender
{
    public function __construct(
        private readonly DriverFactory $drivers,
        private readonly OrderConfirmationMessage $composer,
    ) {}

    /**
     * @return array{ok: bool, error?: string, retryable?: bool}
     */
    public function sendConfirmation(StoreWhatsappIntegration $link, Order $order): array
    {
        if (! $link->canSend()) {
            return ['ok' => false, 'error' => 'الربط مع واتساب مش مكتمل.'];
        }

        $phone = Phone::e164($order->customer_phone);

        if ($phone === '') {
            $this->stampFailure($order, 'الطلب مالوش رقم موبايل صالح.');

            return ['ok' => false, 'error' => 'الطلب مالوش رقم موبايل صالح.'];
        }

        $message = $this->composer->build($link, $order);

        $result = $this->drivers->make($link)->send($phone, $message['body'], $message['variables']);

        WhatsappMessage::create([
            'store_id' => $link->store_id,
            'order_id' => $order->id,
            'direction' => WhatsappMessage::DIRECTION_OUT,
            'phone' => $phone,
            'body' => $message['body'],
            'provider_message_id' => $result->providerMessageId,
            'status' => $result->ok ? 'sent' : 'failed',
            'error' => $result->error,
        ]);

        if (! $result->ok) {
            // A retryable failure is still in flight — marking the order failed
            // now would put it on the merchant's follow-up list for something
            // the next attempt is about to fix.
            if (! $result->retryable) {
                $this->stampFailure($order, (string) $result->error);
            }

            $link->forceFill(['last_error' => $result->error])->save();

            return ['ok' => false, 'error' => $result->error, 'retryable' => $result->retryable];
        }

        $order->forceFill([
            'whatsapp_state' => 'sent',
            'whatsapp_sent_at' => now(),
            'whatsapp_error' => null,
        ])->save();

        $link->forceFill(['last_sent_at' => now(), 'last_error' => null])->save();

        return ['ok' => true];
    }

    private function stampFailure(Order $order, string $error): void
    {
        $order->forceFill([
            'whatsapp_state' => 'failed',
            'whatsapp_error' => $error,
        ])->save();
    }
}

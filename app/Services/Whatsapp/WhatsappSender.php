<?php

namespace App\Services\Whatsapp;

use App\Models\Order;
use App\Models\StoreWhatsappIntegration;
use App\Models\WhatsappMessage;
use App\Services\Billing\UsageBiller;
use App\Support\Phone;
use Illuminate\Support\Facades\Log;

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
        private readonly UsageBiller $biller,
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

        $driver = $this->drivers->make($link);

        // Buttons where the number can carry them; "reply 1 or 2" elsewhere.
        $result = $driver instanceof ConfirmsWithButtons
            ? $driver->sendOrderConfirmation($phone, $message['body'], $message['variables'], $order)
            : $driver->send($phone, $message['body'], $message['variables']);

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

        $this->billConfirmation($order);

        return ['ok' => true];
    }

    /**
     * Charge the confirmation once it has actually gone out — never for a
     * failed attempt, and once per order however often it is re-sent.
     *
     * After the send rather than around it: the customer has the message
     * either way, and a billing hiccup must not report a delivered message as
     * a failure the merchant then re-sends.
     */
    private function billConfirmation(Order $order): void
    {
        try {
            $this->biller->chargeForConfirmation($order->store, $order, "تأكيد واتساب — طلب رقم {$order->number}");
        } catch (\Throwable $e) {
            Log::warning('Failed to bill a WhatsApp confirmation', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * "Your order is confirmed" — straight after the customer taps.
     *
     * Free text, which Meta only delivers inside the 24 hours their tap has
     * just opened, so only on a number that can send it. Best effort: the
     * order has already moved, and a missing receipt is not worth a retry
     * loop.
     */
    public function acknowledge(StoreWhatsappIntegration $link, Order $order, string $intent): void
    {
        $driver = $this->drivers->make($link);
        $phone = Phone::e164($order->customer_phone);

        if (! $driver instanceof ConfirmsWithButtons || ! $link->canSend() || $phone === '') {
            return;
        }

        $body = PlatformWhatsapp::acknowledgement($intent, (string) $order->number, (string) $order->store->name);
        $result = $driver->sendText($phone, $body);

        WhatsappMessage::create([
            'store_id' => $link->store_id,
            'order_id' => $order->id,
            'direction' => WhatsappMessage::DIRECTION_OUT,
            'phone' => $phone,
            'body' => $body,
            'provider_message_id' => $result->providerMessageId,
            'status' => $result->ok ? 'sent' : 'failed',
            'error' => $result->error,
        ]);
    }

    private function stampFailure(Order $order, string $error): void
    {
        $order->forceFill([
            'whatsapp_state' => 'failed',
            'whatsapp_error' => $error,
        ])->save();
    }
}

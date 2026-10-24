<?php

namespace App\Services\Whatsapp;

use App\Models\Order;

/**
 * A driver that can put "تأكيد الطلب" / "إلغاء الطلب" buttons under the message.
 *
 * Separate from WhatsappDriver because only an official number can: WhatsApp
 * does not deliver buttons sent through an unofficial session gateway, and
 * those stores keep the "reply 1 or 2" text.
 */
interface ConfirmsWithButtons
{
    /** @param array<int,string> $variables */
    public function sendOrderConfirmation(string $phone, array $variables, Order $order): SendResult;

    /** Free text inside the window the customer's own reply has just opened. */
    public function sendText(string $phone, string $body): SendResult;
}

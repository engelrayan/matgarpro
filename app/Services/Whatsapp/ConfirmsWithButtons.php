<?php

namespace App\Services\Whatsapp;

use App\Models\Order;

/**
 * A driver that gives the customer something to tap — "تأكيد الطلب" /
 * "إلغاء الطلب" — instead of asking them to type 1 or 2.
 *
 * Buttons on the official number; a poll on our own WAHA gateway, because
 * WhatsApp does not deliver buttons to an unofficial number and does deliver
 * polls. The third-party session gateways keep the typed reply.
 */
interface ConfirmsWithButtons
{
    /**
     * @param  string  $body  the finished message, for a driver that sends text
     * @param  array<int,string>  $variables  the same values, for a template
     */
    public function sendOrderConfirmation(string $phone, string $body, array $variables, Order $order): SendResult;

    /** Free text, straight after the customer's own reply. */
    public function sendText(string $phone, string $body): SendResult;
}

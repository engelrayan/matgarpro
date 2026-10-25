<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Request;

/**
 * A gateway that says when a message we sent reached the phone and was read —
 * the two grey and the two blue ticks in the inbox.
 */
interface ReportsDeliveries
{
    /**
     * @return array{key: string, status: string}|null null when this webhook is not a delivery event;
     *                                                  `key` as in WhatsappMessage::providerKey()
     */
    public function deliveryUpdate(Request $request): ?array;
}

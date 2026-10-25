<?php

namespace App\Services\Whatsapp;

use Illuminate\Http\Request;

/**
 * A gateway that tells us when its linked number connects or drops — so the
 * store stops trying to send through a session that is logged out, and the
 * settings screen says so, without the merchant finding out from a customer.
 */
interface ReportsSessionStatus
{
    /** @return array{status: string, phone: ?string}|null null when this webhook is not a status event */
    public function sessionStatus(Request $request): ?array;
}

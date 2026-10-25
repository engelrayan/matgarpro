<?php

namespace App\Services\Whatsapp;

use App\Models\StoreWhatsappIntegration;
use InvalidArgumentException;

/**
 * Picks the driver a store is connected through.
 *
 * The one place that knows the list: the platform's official number, or the
 * store's own through our WAHA server.
 */
class DriverFactory
{
    public function make(StoreWhatsappIntegration $link): WhatsappDriver
    {
        return match ($link->driver) {
            StoreWhatsappIntegration::DRIVER_PLATFORM => new PlatformDriver(),
            StoreWhatsappIntegration::DRIVER_WAHA => new WahaDriver($link, app(Waha\WahaClient::class)),
            default => throw new InvalidArgumentException("Unknown WhatsApp driver [{$link->driver}]."),
        };
    }
}

<?php

namespace App\Services\Whatsapp;

use App\Models\StoreWhatsappIntegration;
use InvalidArgumentException;

/**
 * Picks the driver a store is connected through.
 *
 * The one place that knows the list. Adding a gateway is a class and a line
 * here — nothing else in the app asks which one is in use.
 */
class DriverFactory
{
    public function make(StoreWhatsappIntegration $link): WhatsappDriver
    {
        return match ($link->driver) {
            StoreWhatsappIntegration::DRIVER_WAPILOT => new WapilotDriver($link),
            StoreWhatsappIntegration::DRIVER_WHATS360 => new Whats360Driver($link),
            StoreWhatsappIntegration::DRIVER_CLOUD_API => new CloudApiDriver($link),
            default => throw new InvalidArgumentException("Unknown WhatsApp driver [{$link->driver}]."),
        };
    }
}

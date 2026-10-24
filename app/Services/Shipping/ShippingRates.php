<?php

namespace App\Services\Shipping;

use App\Models\Product;
use App\Models\Store;

/**
 * What delivery costs a customer of this store, by governorate.
 *
 * The one place that decides it. The order form shows these prices, the
 * checkout validates against them and PlaceOrder charges them, all through
 * here — three copies of "zone price unless the governorate overrides it"
 * would be three chances for the price on screen to differ from the one on
 * the waybill.
 *
 * Stored on the store's settings under `shipping`:
 *
 *   enabled       false until the merchant switches prices on. Off means
 *                 exactly what every store had before this existed: no
 *                 shipping line, nothing added to the total.
 *   carrier       'daman' or 'self' — who carries the parcels.
 *   zones         zone key => price, for the zones in config('egypt.shipping_zones').
 *   governorates  name => ['price' => ?float, 'closed' => bool], for the
 *                 governorates the merchant treats differently from their zone.
 */
class ShippingRates
{
    public const CARRIER_DAMAN = 'daman';

    public const CARRIER_SELF = 'self';

    /** @param array<string,mixed> $settings */
    private function __construct(private readonly Store $store, private readonly array $settings) {}

    public static function for(Store $store): self
    {
        return new self($store, (array) data_get($store->settings, 'shipping', []));
    }

    public function enabled(): bool
    {
        return (bool) ($this->settings['enabled'] ?? false);
    }

    /**
     * Who carries the parcels. A store that never chose is assumed to use
     * Daman if it has connected Daman, and its own courier otherwise.
     */
    public function carrier(): string
    {
        $carrier = $this->settings['carrier'] ?? null;

        if (in_array($carrier, [self::CARRIER_DAMAN, self::CARRIER_SELF], true)) {
            return $carrier;
        }

        return $this->store->damanIntegration?->canShip() ? self::CARRIER_DAMAN : self::CARRIER_SELF;
    }

    public function zonePrice(string $zone): ?float
    {
        $price = $this->settings['zones'][$zone] ?? null;

        return is_numeric($price) ? (float) $price : null;
    }

    public function isClosed(string $governorate): bool
    {
        return (bool) ($this->settings['governorates'][$governorate]['closed'] ?? false);
    }

    /** The governorate's own price, when the merchant set one. */
    public function overridePrice(string $governorate): ?float
    {
        $price = $this->settings['governorates'][$governorate]['price'] ?? null;

        return is_numeric($price) ? (float) $price : null;
    }

    /**
     * Delivery to this governorate, or null when the store does not deliver
     * there: closed by the merchant, priced at nothing, or not a governorate.
     * Only meaningful while prices are enabled.
     */
    public function priceFor(?string $governorate): ?float
    {
        $zone = self::zoneOf((string) $governorate);

        if ($zone === null || $this->isClosed($governorate)) {
            return null;
        }

        return $this->overridePrice($governorate) ?? $this->zonePrice($zone);
    }

    /**
     * The governorates a customer may pick, with their price, in the order the
     * form lists them (most populous first — see config/egypt.php).
     *
     * @return array<string,float>
     */
    public function openGovernorates(): array
    {
        $open = [];

        foreach ((array) config('egypt.governorates') as $governorate) {
            $price = $this->priceFor($governorate);

            if ($price !== null) {
                $open[$governorate] = $price;
            }
        }

        return $open;
    }

    /**
     * What this order's delivery costs the customer.
     *
     * Once per order, whatever the quantity. Free when prices are off, and
     * free when every product in it is marked free-shipping — one paid
     * product in the parcel and the parcel is paid for.
     *
     * @param  iterable<Product>  $products
     * @return float|null null when the store does not deliver to that governorate
     */
    public function feeFor(?string $governorate, iterable $products): ?float
    {
        if (! $this->enabled()) {
            return 0.0;
        }

        $price = $this->priceFor($governorate);

        if ($price === null) {
            return null;
        }

        foreach ($products as $product) {
            if (! $product->pageSettings()['free_shipping']) {
                return $price;
            }
        }

        return 0.0;
    }

    /** The zone a governorate belongs to. */
    public static function zoneOf(string $governorate): ?string
    {
        foreach ((array) config('egypt.shipping_zones') as $key => $zone) {
            if (in_array($governorate, $zone['governorates'], true)) {
                return $key;
            }
        }

        return null;
    }
}

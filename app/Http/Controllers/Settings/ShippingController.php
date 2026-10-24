<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Shipping\ShippingRates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who carries the merchant's parcels, and what delivery costs the customer.
 *
 * Prices are set per zone and overridden per governorate; see ShippingRates
 * for how they are read, and config/egypt.php for the zones themselves.
 */
class ShippingController extends Controller
{
    public function edit(Request $request): Response
    {
        $store = $request->user()->currentStore();
        $rates = ShippingRates::for($store);

        return Inertia::render('settings/Shipping', [
            'enabled' => $rates->enabled(),
            'carrier' => $rates->carrier(),
            'damanConnected' => (bool) $store->damanIntegration?->canShip(),
            'currency' => $store->currency,
            'zones' => collect((array) config('egypt.shipping_zones'))
                ->map(fn (array $zone, string $key) => [
                    'key' => $key,
                    'label' => $zone['label'],
                    'price' => $rates->zonePrice($key),
                    'governorates' => array_map(fn (string $name) => [
                        'name' => $name,
                        'price' => $rates->overridePrice($name),
                        'closed' => $rates->isClosed($name),
                    ], $zone['governorates']),
                ])
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $store = $request->user()->currentStore();

        $price = ['nullable', 'numeric', 'min:0', 'max:100000'];

        $validated = $request->validate([
            'enabled' => ['boolean'],
            'carrier' => ['required', Rule::in([ShippingRates::CARRIER_DAMAN, ShippingRates::CARRIER_SELF])],
            'zones' => ['array'],
            'zones.*' => $price,
            'governorates' => ['array'],
            'governorates.*.price' => $price,
            'governorates.*.closed' => ['boolean'],
        ], [
            'zones.*.numeric' => 'السعر لازم يكون رقم.',
            'zones.*.min' => 'السعر مينفعش يبقى بالسالب.',
            'governorates.*.price.numeric' => 'السعر لازم يكون رقم.',
            'governorates.*.price.min' => 'السعر مينفعش يبقى بالسالب.',
        ]);

        $zones = (array) config('egypt.shipping_zones');

        // Only zones and governorates we ship. A posted key with no zone
        // behind it would be stored and never read — or worse, read by a
        // future zone that happens to take its name.
        $zonePrices = [];
        foreach (array_keys($zones) as $key) {
            $value = $validated['zones'][$key] ?? null;
            $zonePrices[$key] = is_numeric($value) ? round((float) $value, 2) : null;
        }

        $governorates = [];
        foreach ($zones as $zone) {
            foreach ($zone['governorates'] as $name) {
                $row = $validated['governorates'][$name] ?? [];
                $override = is_numeric($row['price'] ?? null) ? round((float) $row['price'], 2) : null;
                $closed = (bool) ($row['closed'] ?? false);

                // Stored only when it differs from the zone, so the settings
                // stay a short list of exceptions rather than 27 copies.
                if ($override !== null || $closed) {
                    $governorates[$name] = ['price' => $override, 'closed' => $closed];
                }
            }
        }

        $settings = [
            'enabled' => (bool) ($validated['enabled'] ?? false),
            'carrier' => $validated['carrier'],
            'zones' => $zonePrices,
            'governorates' => $governorates,
        ];

        if ($settings['enabled']) {
            $this->ensureEveryOpenGovernorateHasAPrice($zones, $settings);
        }

        $store->update(['settings' => [...(array) $store->settings, 'shipping' => $settings]]);

        return back()->with('status', 'shipping-updated');
    }

    /**
     * With prices on, a governorate that is open but has no price would
     * silently drop out of the order form. Say so on the zone instead.
     *
     * @param  array<string,array<string,mixed>>  $zones
     * @param  array<string,mixed>  $settings
     */
    private function ensureEveryOpenGovernorateHasAPrice(array $zones, array $settings): void
    {
        $errors = [];
        $anyOpen = false;

        foreach ($zones as $key => $zone) {
            foreach ($zone['governorates'] as $name) {
                $row = $settings['governorates'][$name] ?? ['price' => null, 'closed' => false];

                if ($row['closed']) {
                    continue;
                }

                $anyOpen = true;

                if ($row['price'] === null && $settings['zones'][$key] === null) {
                    $errors["zones.{$key}"] = 'حط سعر الشحن للقطاع ده، أو اقفل المحافظات اللي مش بتشحنلها.';
                }
            }
        }

        if (! $anyOpen) {
            $errors['enabled'] = 'لازم محافظة واحدة على الأقل تكون مفتوحة، وإلا مفيش حد هيقدر يطلب.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}

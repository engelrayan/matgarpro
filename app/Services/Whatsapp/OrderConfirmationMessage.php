<?php

namespace App\Services\Whatsapp;

use App\Models\Order;
use App\Models\StoreWhatsappIntegration;

/**
 * The message about one order, in the merchant's own words.
 *
 * Returns the finished text and, separately, the values that went into it —
 * Meta's templates take ordered parameters rather than a string, so both shapes
 * come out of one place and can never drift apart.
 */
class OrderConfirmationMessage
{
    /** The order of the values a Cloud API template body receives. */
    public const VARIABLE_ORDER = ['{name}', '{number}', '{items}', '{total}', '{currency}'];

    /**
     * @return array{body: string, variables: array<int,string>}
     */
    public function build(StoreWhatsappIntegration $link, Order $order): array
    {
        $order->loadMissing('items', 'store');

        $values = [
            '{store}' => (string) $order->store->name,
            '{name}' => (string) $order->customer_name,
            '{number}' => (string) $order->number,
            '{items}' => $this->items($order),
            '{total}' => number_format((float) $order->total, 2),
            '{currency}' => (string) $order->store->currency,
        ];

        return [
            'body' => strtr($link->template(), $values),
            'variables' => array_map(
                // A template parameter may not contain a newline — Meta rejects
                // the whole message for it, and a multi-line item list is the
                // obvious way to hit that.
                fn (string $key) => str_replace("\n", ' · ', $values[$key] ?? ''),
                self::VARIABLE_ORDER,
            ),
        ];
    }

    private function items(Order $order): string
    {
        return $order->items
            ->map(fn ($item) => '• ' . $item->quantity . '× ' . $item->name
                . ($item->variant_label ? " ({$item->variant_label})" : ''))
            ->implode("\n");
    }
}

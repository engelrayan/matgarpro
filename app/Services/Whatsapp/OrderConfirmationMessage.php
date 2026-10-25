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

        // Total and currency as one value: the platform template has a single
        // slot for the amount.
        $values['{amount}'] = $values['{total}'] . ' ' . $values['{currency}'];

        // The platform's template takes the shop's name, because the message
        // comes from our number rather than theirs.
        $slots = $link->isPlatform() ? PlatformWhatsapp::VARIABLE_ORDER : self::VARIABLE_ORDER;

        // On WAHA the whole message is a poll title, which WhatsApp caps: the
        // products go on one line, and if the order is long it is the list
        // that gets shortened — never the "please confirm" the merchant wrote
        // after it.
        if ($link->isWaha()) {
            $values['{items}'] = $this->itemsFitting($order, $link->template(), $values);
        }

        return [
            // «من متجر {store}» for a shop already called «متجر …» would say
            // the word twice.
            'body' => str_replace('متجر متجر', 'متجر', strtr($link->template(), $values)),
            'variables' => array_map(
                // A template parameter may not contain a newline — Meta rejects
                // the whole message for it, and a multi-line item list is the
                // obvious way to hit that.
                fn (string $key) => str_replace("\n", ' · ', $values[$key] ?? ''),
                $slots,
            ),
        ];
    }

    /**
     * The products on one line, as long as the poll title has room for:
     * whole items while they fit, then «و N كمان».
     *
     * @param  array<string,string>  $values
     */
    private function itemsFitting(Order $order, string $template, array $values): string
    {
        $room = WahaDriver::POLL_TITLE_LIMIT - mb_strlen(strtr($template, [...$values, '{items}' => '']));

        $names = $order->items->map(fn ($item) => $item->quantity . '× ' . $item->name
            . ($item->variant_label ? " ({$item->variant_label})" : ''))->values();

        for ($shown = $names->count(); $shown > 0; $shown--) {
            $line = $names->take($shown)->implode('، ');

            if ($shown < $names->count()) {
                $line .= ' و' . ($names->count() - $shown) . ' كمان';
            }

            if (mb_strlen($line) <= $room) {
                return $line;
            }
        }

        // Not even one item fits: the count alone.
        return $names->count() . ' منتج';
    }

    private function items(Order $order): string
    {
        return $order->items
            ->map(fn ($item) => '• ' . $item->quantity . '× ' . $item->name
                . ($item->variant_label ? " ({$item->variant_label})" : ''))
            ->implode("\n");
    }
}

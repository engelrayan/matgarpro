<?php

namespace App\Support;

/**
 * Converts Arabic-Indic and Persian digits to Latin ones.
 *
 * Arabic keyboards produce ٠١٢…, and every naive `preg_replace('/\D+/')`
 * deletes them outright because they are multi-byte. The failure is silent:
 * a phone number becomes an empty string, an order is taken that nobody can
 * call, and an ad conversion is reported with no phone to match on.
 *
 * One copy, used everywhere a typed number is read.
 */
class ArabicNumerals
{
    private const MAP = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        // Persian (U+06F0–U+06F9) — visually near-identical and produced by
        // some Arabic keyboard layouts.
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    public static function toLatin(string $value): string
    {
        return strtr($value, self::MAP);
    }

    /** Latin digits only — everything else removed. */
    public static function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', self::toLatin($value)) ?? '';
    }
}

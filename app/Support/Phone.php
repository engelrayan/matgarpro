<?php

namespace App\Support;

/**
 * One shape for a phone number, so a reply can find its order.
 *
 * The same customer is `01006262330` in the checkout form, `201006262330` in a
 * WhatsApp webhook, and `+20 100 626 2330` when somebody pastes it from their
 * contacts. Matching those by string equality never works, and the failure is
 * silent — the reply arrives, nothing matches, the order sits unconfirmed.
 */
class Phone
{
    /** Egypt. The default the storefront is built for. */
    private const DEFAULT_COUNTRY_CODE = '20';

    /**
     * E.164 digits with no plus — `201006262330`.
     *
     * A local number keeps its country code; anything already international is
     * left as it is, because guessing at a number we did not ask for is worse
     * than not matching it.
     */
    public static function e164(?string $value): string
    {
        $digits = ArabicNumerals::digitsOnly((string) $value);

        if ($digits === '') {
            return '';
        }

        // 01xxxxxxxxx → 201xxxxxxxxx
        if (str_starts_with($digits, '0')) {
            return self::DEFAULT_COUNTRY_CODE . substr($digits, 1);
        }

        // Already carries the country code.
        if (str_starts_with($digits, self::DEFAULT_COUNTRY_CODE)) {
            return $digits;
        }

        // A bare 10-digit Egyptian mobile, typed without its leading zero.
        if (strlen($digits) === 10 && str_starts_with($digits, '1')) {
            return self::DEFAULT_COUNTRY_CODE . $digits;
        }

        return $digits;
    }

    /** True when two numbers are the same person, however either was typed. */
    public static function same(?string $a, ?string $b): bool
    {
        $left = self::e164($a);

        return $left !== '' && $left === self::e164($b);
    }
}

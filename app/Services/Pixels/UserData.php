<?php

namespace App\Services\Pixels;

use App\Support\ArabicNumerals;

/**
 * Builds Meta's hashed customer-data block.
 *
 * Every identifier is normalised the way Meta specifies and then SHA-256'd.
 * Normalisation is not cosmetic: Meta matches on the hash, so "  Ahmed " and
 * "ahmed" produce different hashes and the second one silently fails to match
 * anybody. Match quality is exactly this function's correctness.
 *
 * Raw values never leave this class — only digests go over the wire.
 */
class UserData
{
    /**
     * @param  array<string,string|null>  $fields
     * @return array<string,mixed>
     */
    public static function build(
        ?string $email = null,
        ?string $phone = null,
        ?string $name = null,
        ?string $city = null,
        ?string $state = null,
        ?string $fbp = null,
        ?string $fbc = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): array {
        $data = array_filter([
            'em' => self::hash(self::normaliseEmail($email)),
            'ph' => self::hash(self::normalisePhone($phone)),
            'ct' => self::hash(self::normaliseText($city)),
            'st' => self::hash(self::normaliseText($state)),
            // Egypt-only storefronts today; the country code is still part of
            // the match key and costs nothing to send.
            'country' => self::hash('eg'),
        ]);

        [$first, $last] = self::splitName($name);

        if ($first) {
            $data['fn'] = self::hash($first);
        }

        if ($last) {
            $data['ln'] = self::hash($last);
        }

        /*
         | `fbp` and `fbc` are NOT hashed — Meta expects them verbatim, and
         | hashing them makes the event unmatchable. They are also the single
         | strongest signal available, because they identify the exact click.
         */
        return array_filter([
            ...$data,
            'fbp' => $fbp ?: null,
            'fbc' => $fbc ?: null,
            'client_ip_address' => $ip ?: null,
            'client_user_agent' => $userAgent ?: null,
        ]);
    }

    private static function hash(?string $value): ?string
    {
        return $value ? hash('sha256', $value) : null;
    }

    private static function normaliseEmail(?string $email): ?string
    {
        $email = trim(mb_strtolower((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Digits only, in international form without a plus.
     *
     * Egyptian mobiles are stored locally as `01xxxxxxxxx`; Meta wants
     * `201xxxxxxxxx`. Sending the local form matches nobody.
     */
    private static function normalisePhone(?string $phone): ?string
    {
        // Via the shared helper: a plain `\D` strip deletes Arabic-Indic digits
        // outright, leaving an empty string and an event with no phone to match.
        $digits = ArabicNumerals::digitsOnly((string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '01') && strlen($digits) === 11) {
            $digits = '20' . substr($digits, 1);
        }

        return strlen($digits) >= 8 ? $digits : null;
    }

    private static function normaliseText(?string $value): ?string
    {
        $value = preg_replace('/\s+/u', '', mb_strtolower(trim((string) $value))) ?? '';

        return $value !== '' ? $value : null;
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return [null, null];
        }

        $first = self::normaliseText(array_shift($parts));
        $last = $parts === [] ? null : self::normaliseText(implode(' ', $parts));

        return [$first, $last];
    }
}

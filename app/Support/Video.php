<?php

namespace App\Support;

/**
 * Pulls a YouTube id out of whatever the merchant pasted.
 *
 * Merchants paste the address bar, the share sheet, a Shorts link, or just the
 * id — and an embed built by string-concatenating the raw input is an iframe
 * whose `src` a merchant controls. Extracting the id means the URL the browser
 * loads is one this code wrote, from eleven known-safe characters.
 */
class Video
{
    public static function youtubeId(?string $input): ?string
    {
        $value = trim((string) $input);

        if ($value === '') {
            return null;
        }

        // Already an id.
        if (preg_match('#^[A-Za-z0-9_-]{11}$#', $value)) {
            return $value;
        }

        $patterns = [
            '#youtu\.be/([A-Za-z0-9_-]{11})#',
            '#[?&]v=([A-Za-z0-9_-]{11})#',
            '#youtube\.com/embed/([A-Za-z0-9_-]{11})#',
            '#youtube\.com/shorts/([A-Za-z0-9_-]{11})#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}

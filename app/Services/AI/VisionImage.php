<?php

namespace App\Services\AI;

/**
 * Shrinks a photo to what Claude actually looks at.
 *
 * Claude bills an image by its pixels (about one token per 750), and scales
 * anything over ~1.15 megapixels down before looking at it anyway. A phone
 * photo sent as-is costs the same as one sent at 1024px and adds seconds of
 * upload — and a 5MB original can exceed the API's per-image limit once
 * base64-encoded. So every image is re-encoded here, once, before it is stored.
 */
class VisionImage
{
    /** Long edge, in pixels. ~1,400 tokens for a square photo. */
    public const MAX_EDGE = 1024;

    /**
     * @return array{bytes:string,media_type:string}|null null when the file is not
     *                                                     an image we can read
     */
    public static function prepare(string $path): ?array
    {
        $raw = @file_get_contents($path);

        if ($raw === false || $raw === '') {
            return null;
        }

        if (! function_exists('imagecreatefromstring')) {
            return self::passthrough($raw);
        }

        $source = @imagecreatefromstring($raw);

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        // White under transparent PNGs; JPEG has no alpha and would turn it black.
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 85);
        $bytes = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return ['bytes' => $bytes, 'media_type' => 'image/jpeg'];
    }

    /**
     * Without GD, send the original if it is small enough to be accepted.
     *
     * @return array{bytes:string,media_type:string}|null
     */
    private static function passthrough(string $raw): ?array
    {
        // 5MB API limit applies to the base64 text, which is 4/3 the size.
        if (strlen($raw) > 3_700_000) {
            return null;
        }

        $type = (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw);

        return in_array($type, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)
            ? ['bytes' => $raw, 'media_type' => $type]
            : null;
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Public URL for a stored path.
 *
 * A one-liner with a name, because the alternative is
 * `Storage::disk('public')->url(...)` repeated across twenty section blades —
 * and the day the storefront's images move to a CDN, twenty blades is twenty
 * chances to miss one.
 */
class Media
{
    public static function url(?string $path): ?string
    {
        return filled($path) ? Storage::disk('public')->url($path) : null;
    }
}

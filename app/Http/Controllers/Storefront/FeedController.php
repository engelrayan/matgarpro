<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Catalog\ProductFeed;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Serves the product feed on the merchant's own storefront domain.
 *
 * Public, with no token. Everything in the feed — names, prices, images, links
 * — is already on the storefront for any visitor to read, so a secret URL would
 * protect nothing while giving the merchant one more thing to lose. It also
 * keeps the setup honest: paste the link, done.
 */
class FeedController extends Controller
{
    /** Platforms that read the same RSS shape. */
    private const PLATFORMS = ['meta', 'google', 'tiktok'];

    public function __invoke(Store $store, string $platform, ProductFeed $feed): Response
    {
        abort_unless(in_array($platform, self::PLATFORMS, true), 404);

        // A showroom's catalogue is fiction. Letting it reach an ad platform
        // would put products nobody can buy into a real shopping surface.
        abort_if($store->is_demo, 404);

        /*
         | Cached for an hour.
         |
         | Platforms fetch on their own schedule and retry on failure, so the
         | same feed can be pulled several times in a row. Building it from the
         | database each time would mean a merchant's whole catalogue queried
         | on someone else's cron.
         |
         | Keyed by the store's updated_at so a price change invalidates it
         | without waiting the hour out.
         */
        $xml = Cache::remember(
            "feed:{$platform}:{$store->id}:" . $store->products()->max('updated_at'),
            now()->addHour(),
            fn () => $feed->build($store, $platform),
        );

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            // Crawlers index feed URLs otherwise, and a raw XML dump in search
            // results competes with the merchant's own product pages.
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}

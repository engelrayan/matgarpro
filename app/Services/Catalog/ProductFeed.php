<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Str;

/**
 * Builds the product feed the ad platforms pull.
 *
 * A feed URL rather than an API push, deliberately. Meta, Google and TikTok all
 * accept a scheduled fetch, and that path needs no app review, no OAuth and no
 * token the merchant has to keep alive — the same reasoning as the pixel page.
 * They paste one link and the catalogue stays current on its own.
 *
 * RSS 2.0 with the `g:` namespace: it is the one format all three read, so one
 * builder feeds every platform instead of three that drift apart.
 */
class ProductFeed
{
    /**
     * @param  string  $platform  Shapes the few fields that genuinely differ.
     */
    public function build(Store $store, string $platform = 'meta'): string
    {
        $items = $this->items($store, $platform);

        $title = $this->escape($store->name);
        $link = $this->escape($store->canonicalUrl());
        $description = $this->escape($store->description ?: $store->name);

        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">
          <channel>
            <title>{$title}</title>
            <link>{$link}</link>
            <description>{$description}</description>
        {$items}
          </channel>
        </rss>
        XML;
    }

    private function items(Store $store, string $platform): string
    {
        $products = $store->products()
            ->with(['images', 'variants'])
            ->where('status', Product::STATUS_ACTIVE)
            // No picture, no listing. Every platform rejects an item without an
            // image, and a feed full of rejections reads to the merchant as our
            // integration being broken.
            ->has('images')
            ->orderBy('sort_order')
            ->get();

        $out = [];

        foreach ($products as $product) {
            if ($product->isHidden()) {
                continue;
            }

            if ($product->hasVariants()) {
                /*
                 | One entry per variant, tied together by `item_group_id`.
                 |
                 | A clothing product listed as a single item shows one size and
                 | the platform treats a sold-out medium as the whole product
                 | being gone. Grouped variants are what make "أحمر · L" its own
                 | buyable row.
                 */
                foreach ($product->variants as $variant) {
                    $out[] = $this->item($store, $product, $platform, $variant);
                }

                continue;
            }

            $out[] = $this->item($store, $product, $platform);
        }

        return implode("\n", $out);
    }

    private function item(Store $store, Product $product, string $platform, ?ProductVariant $variant = null): string
    {
        $id = $variant ? "{$product->id}-{$variant->id}" : (string) $product->id;
        $title = $product->name . ($variant ? ' — ' . $variant->label() : '');

        $price = (float) ($variant?->effectivePrice($product) ?? $product->price);
        $stock = $variant?->stock ?? $product->stock;
        $available = ! $product->track_stock || $stock > 0;

        $link = $store->canonicalUrl() . '/p/' . $product->slug;

        /*
         | Absolute, always.
         |
         | The disk returns `/storage/...`, and every platform fetches the feed
         | from its own servers where a root-relative path resolves to nothing.
         | A whole catalogue gets rejected for exactly this.
         */
        $image = $product->primaryImage()?->url();
        $image = $image && ! Str::startsWith($image, ['http://', 'https://'])
            ? $store->canonicalUrl() . '/' . ltrim($image, '/')
            : $image;

        // Plain text: the description holds sanitised HTML for the storefront,
        // and every platform rejects markup in this field. Block tags become a
        // space first — stripping them bare runs "الاستلام" into "استبدال".
        $plain = strip_tags(preg_replace('#</(p|li|h[1-6]|div|br)>|<br\s*/?>#i', ' ', (string) $product->description));

        $description = Str::limit(
            trim(preg_replace('/\s+/u', ' ', $plain)) ?: $product->name,
            4000,
            '',
        );

        $fields = [
            'g:id' => $id,
            'g:title' => Str::limit($title, 150, ''),
            'g:description' => $description,
            'g:link' => $link,
            'g:image_link' => $image,
            'g:availability' => $available ? 'in stock' : 'out of stock',
            'g:condition' => 'new',
            'g:price' => number_format($price, 2, '.', '') . ' ' . $store->currency,
            // `$store`, not `$product->store` — the relation would be a query
            // per row, and strict mode rejects the lazy load outright.
            'g:brand' => $store->name,
        ];

        // A struck-through price only means something when there is a real sale
        // running; sending it otherwise makes every product look discounted.
        if ($product->isOnSale()) {
            $fields['g:price'] = number_format((float) $product->compare_at_price, 2, '.', '') . ' ' . $store->currency;
            $fields['g:sale_price'] = number_format($price, 2, '.', '') . ' ' . $store->currency;

            if ($deadline = $product->saleDeadline()) {
                $fields['g:sale_price_effective_date'] =
                    now()->toIso8601String() . '/' . $deadline->toIso8601String();
            }
        }

        if ($variant) {
            $fields['g:item_group_id'] = (string) $product->id;

            // Option names are the merchant's own words ("المقاس"), so they are
            // mapped onto the platform's fixed attributes by position rather
            // than by name — a name match would only work in English.
            foreach (array_values($variant->options ?? []) as $i => $value) {
                $fields[['g:size', 'g:color', 'g:material'][$i] ?? 'g:pattern'] = $value;
            }
        }

        if ($product->sku) {
            $fields['g:mpn'] = $product->sku;
        }

        // Lets Meta stop serving an item the moment the last one sells, instead
        // of waiting for the next scheduled fetch.
        if ($platform === 'meta' && $product->track_stock) {
            $fields['g:quantity_to_sell_on_facebook'] = (string) max(0, $stock);
        }

        $xml = ['    <item>'];

        foreach ($fields as $tag => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $xml[] = "      <{$tag}>" . $this->escape((string) $value) . "</{$tag}>";
        }

        $xml[] = '    </item>';

        return implode("\n", $xml);
    }

    /**
     * CDATA rather than entity escaping.
     *
     * Product names in this market carry `&`, `<` and quotes routinely, and a
     * single unescaped one invalidates the whole document — the platform then
     * reports "feed could not be parsed" with no hint which row did it.
     */
    private function escape(string $value): string
    {
        // A nested terminator would close the section early; split it so the
        // text survives verbatim.
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $value) . ']]>';
    }
}

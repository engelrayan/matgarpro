<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Copies a product, its images and its variants.
 *
 * Merchants selling variations of one item (same photos, different print) add
 * products this way far more often than from scratch, so this is a headline
 * button rather than something buried in a menu.
 */
class DuplicateProduct
{
    public function handle(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $product->loadMissing(['images', 'variants']);

            $name = $product->name . ' — نسخة';

            $copy = $product->store->products()->create([
                'name' => $name,
                'slug' => Product::uniqueSlug($product->store_id, $name),
                'description' => $product->description,
                'price' => $product->price,
                'compare_at_price' => $product->compare_at_price,
                'cost' => $product->cost,
                // SKU deliberately not copied: it is meant to be unique, and a
                // duplicate one silently breaks the merchant's own stock sheets.
                'track_stock' => $product->track_stock,
                'stock' => $product->stock,
                'options' => $product->options,
                // Always a draft. A copy is half-edited by definition, and
                // publishing it live would show customers the wrong thing.
                'status' => Product::STATUS_DRAFT,
                'seo_title' => $product->seo_title,
                'seo_description' => $product->seo_description,
            ]);

            foreach ($product->images as $image) {
                // Copy the file rather than share the path, so deleting one
                // product's images cannot blank out the other's.
                $path = "stores/{$copy->store_id}/products/" . uniqid() . '.' . pathinfo($image->path, PATHINFO_EXTENSION);

                if (Storage::disk('public')->exists($image->path)) {
                    Storage::disk('public')->copy($image->path, $path);
                }

                $copy->images()->create([
                    'path' => $path,
                    'alt' => $image->alt,
                    'sort_order' => $image->sort_order,
                ]);
            }

            foreach ($product->variants as $variant) {
                $copy->variants()->create([
                    'options' => $variant->options,
                    'price' => $variant->price,
                    'stock' => $variant->stock,
                ]);
            }

            return $copy;
        });
    }
}

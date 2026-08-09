<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Support\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Creates or updates a product together with its images and variants.
 *
 * One action for both because the reconciliation rules — which images survive,
 * which variants keep their stock — are the same either way, and splitting them
 * is how "create works, edit silently loses your stock" happens.
 */
class SaveProduct
{
    /**
     * @param  array<string,mixed>  $data
     * @param  array<int,UploadedFile>  $newImages
     */
    public function handle(Store $store, array $data, array $newImages = [], ?Product $product = null): Product
    {
        return DB::transaction(function () use ($store, $data, $newImages, $product) {
            $product = $product
                ? tap($product)->update($this->attributes($store, $data, $product))
                : $store->products()->create($this->attributes($store, $data));

            $this->syncImages($product, $data, $newImages);
            $this->syncVariants($product, $data);
            $this->syncCategories($store, $product, $data);

            return $product->fresh(['images', 'variants', 'categories']);
        });
    }

    /** @param array<string,mixed> $data */
    private function attributes(Store $store, array $data, ?Product $product = null): array
    {
        $name = $data['name'];

        return [
            'name' => $name,
            // Merchants may set the URL by hand; when they leave it blank we
            // derive it, and we only re-derive on create so that editing a
            // product's name never breaks links already running in ads.
            'slug' => Product::uniqueSlug(
                $store->id,
                $data['slug'] ?: $name,
                $product?->id,
            ),
            // Sanitised here, not in the request: every path that saves a
            // product goes through this action, and the description is
            // rendered unescaped on a page customers buy from.
            'description' => HtmlSanitizer::clean($data['description'] ?? null),
            // Blank is stored as NULL, not "": the storefront tests `filled()`
            // on this, and an empty string would render a video block with
            // nothing in it.
            'video_url' => filled($data['video_url'] ?? null) ? trim($data['video_url']) : null,
            'price' => $data['price'],
            'compare_at_price' => $data['compare_at_price'] ?? null,
            'cost' => $data['cost'] ?? null,
            'sku' => $data['sku'] ?? null,
            'track_stock' => $data['track_stock'] ?? true,
            'stock' => $data['stock'] ?? 0,
            'options' => $this->cleanOptions($data['options'] ?? []),
            'settings' => $this->cleanSettings($data['settings'] ?? []),
            'status' => $data['status'] ?? Product::STATUS_ACTIVE,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
        ];
    }

    /**
     * Drop options with no name or no values.
     *
     * An option row the merchant started typing and abandoned would otherwise
     * render as an empty selector the customer cannot satisfy, making the
     * product impossible to buy.
     *
     * @param  array<int,array<string,mixed>>  $options
     */
    private function cleanOptions(array $options): ?array
    {
        $clean = [];

        foreach ($options as $option) {
            $name = trim((string) ($option['name'] ?? ''));
            $values = array_values(array_filter(array_map(
                fn ($v) => trim((string) $v),
                $option['values'] ?? [],
            ), fn ($v) => $v !== ''));

            if ($name !== '' && $values !== []) {
                $clean[] = ['name' => $name, 'values' => $values];
            }
        }

        return $clean ?: null;
    }

    /**
     * Attach the product to its categories.
     *
     * The ids are re-checked against the store rather than trusted from the
     * form: a posted id from another merchant's store would otherwise file
     * this product under a section its owner never created.
     *
     * @param  array<string,mixed>  $data
     */
    private function syncCategories(Store $store, Product $product, array $data): void
    {
        if (! array_key_exists('categories', $data)) {
            return;
        }

        $owned = $store->categories()
            ->whereIn('id', (array) ($data['categories'] ?? []))
            ->pluck('id');

        $product->categories()->sync($owned);
    }

    /**
     * Keep only the settings we ship, cast to the right types.
     *
     * Storing the posted array as-is would let anything ride along into a JSON
     * column the storefront then reads, and a "true" string from a form post
     * is not the same as a boolean once it reaches a Blade condition.
     *
     * @param  array<string,mixed>  $settings
     * @return array<string,mixed>
     */
    private function cleanSettings(array $settings): array
    {
        $defaults = (array) config('products.defaults');
        $clean = [];

        foreach ($defaults as $key => $default) {
            if (! array_key_exists($key, $settings)) {
                continue;
            }

            $clean[$key] = is_bool($default)
                ? filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN)
                : trim((string) $settings[$key]);
        }

        // An empty button label would render a button with no words on it.
        if (($clean['buy_button_text'] ?? '') === '') {
            unset($clean['buy_button_text']);
        }

        return $clean;
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  array<int,UploadedFile>  $newImages
     */
    private function syncImages(Product $product, array $data, array $newImages): void
    {
        // `kept` is the ordered list of existing image ids the client still
        // shows. Anything absent was removed in the UI.
        $kept = collect($data['kept_images'] ?? [])->map(fn ($id) => (int) $id);

        $product->images()
            ->whereNotIn('id', $kept->all())
            ->get()
            ->each(function (ProductImage $image) {
                Storage::disk('public')->delete($image->path);
                $image->delete();
            });

        $kept->each(function (int $id, int $index) use ($product) {
            $product->images()->whereKey($id)->update(['sort_order' => $index]);
        });

        $next = $kept->count();

        foreach ($newImages as $file) {
            $product->images()->create([
                'path' => $file->store("stores/{$product->store_id}/products", 'public'),
                'alt' => $product->name,
                'sort_order' => $next++,
            ]);
        }
    }

    /**
     * Reconcile variant rows against the combinations the client sent.
     *
     * Matched by option combination rather than id: the UI regenerates the
     * matrix whenever options change, so ids are not stable but the physical
     * variant "أحمر · L" is — and its stock count must survive the edit.
     *
     * @param  array<string,mixed>  $data
     */
    private function syncVariants(Product $product, array $data): void
    {
        if (! $product->hasVariants()) {
            $product->variants()->delete();

            return;
        }

        $existing = $product->variants()->get()->keyBy(fn (ProductVariant $v) => $v->key());
        $seen = [];

        foreach ($data['variants'] ?? [] as $row) {
            $options = array_filter((array) ($row['options'] ?? []));

            if ($options === []) {
                continue;
            }

            $key = ProductVariant::keyFor($options);
            $seen[] = $key;

            $attributes = [
                'options' => $options,
                'price' => ($row['price'] ?? null) === '' ? null : $row['price'] ?? null,
                'stock' => (int) ($row['stock'] ?? 0),
                'sku' => $row['sku'] ?? null,
            ];

            if ($match = $existing->get($key)) {
                $match->update($attributes);
            } else {
                $product->variants()->create($attributes);
            }
        }

        /*
         | Deliberately not $existing->except($seen): on an Eloquent collection
         | except() matches primary keys, not array keys, so it would never
         | recognise these composite keys and would delete every variant —
         | taking the merchant's stock counts with it.
         */
        foreach ($existing as $key => $variant) {
            if (! in_array($key, $seen, true)) {
                $variant->delete();
            }
        }
    }
}

<?php

namespace App\Services\AI\StoreBuilder;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\Builder\PageBuilder;
use App\Services\Builder\SectionRegistry;
use App\Services\Demo\DemoArtwork;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Puts an accepted store plan into the store — as drafts wherever a draft
 * exists.
 *
 *  - Home, header and footer go into the builder's draft. Customers keep
 *    seeing the live pages until the merchant presses نشر in the builder.
 *  - Categories are created empty. The storefront never shows an empty
 *    category anywhere (see SectionData::categories), so they stay invisible
 *    until the merchant files products under them.
 *  - The theme has no draft: it is a store setting the storefront reads
 *    directly. It is applied only when the merchant ticked it, on a screen
 *    that says plainly that it changes the live look.
 *  - Starter products are created as drafts, each with its photo. The
 *    builder's preview shows them (see self::SAMPLES_SETTING); customers
 *    never do until the merchant edits one and makes it active.
 *
 * Every section goes through PageBuilder::saveDraft, so the sanitiser rebuilds
 * it from the registry exactly as it would a merchant's own edit. Nothing the
 * model wrote reaches a blade without that pass.
 */
class StorePlanApplier
{
    /**
     * Where the store keeps the ids of its starter products: the builder's
     * preview shows these drafts, and the next plan replaces them.
     */
    public const SAMPLES_SETTING = 'ai_samples';

    public function __construct(
        private readonly PageBuilder $builder,
        private readonly SectionRegistry $registry,
        private readonly DemoArtwork $artwork,
    ) {}

    /**
     * @param  array<string,mixed>  $plan  a StorePlanWriter result
     * @param  array<int,string>  $categoryNames  the merchant's final list, after their edits
     * @param  array<string,string>  $social  platform => link, from the merchant's own answers
     * @return array{categories:int,products:int}
     */
    public function apply(Store $store, array $plan, array $categoryNames, bool $applyTheme, array $social = []): array
    {
        return DB::transaction(function () use ($store, $plan, $categoryNames, $applyTheme, $social) {
            $created = $this->createCategories($store, $categoryNames, (array) ($plan['categories'] ?? []));
            $products = $this->starterProducts($store, (array) ($plan['products'] ?? []));

            $this->builder->saveDraft($store, 'home', $this->home($plan));
            $this->builder->saveDraft($store, 'header', $this->header($plan));
            $this->builder->saveDraft($store, 'footer', $this->footer($plan, $social));

            if ($applyTheme && array_key_exists($plan['theme'] ?? '', (array) config('themes.themes'))) {
                $store->update(['settings' => [...(array) $store->settings, 'theme' => $plan['theme']]]);
            }

            return ['categories' => $created, 'products' => $products];
        });
    }

    /**
     * Starter products still waiting as drafts — what a publish can offer to
     * put live with the pages.
     */
    public function starterDrafts(Store $store): int
    {
        $ids = (array) data_get($store->settings, self::SAMPLES_SETTING, []);

        return $ids === [] ? 0 : $store->products()->whereIn('id', $ids)->where('status', Product::STATUS_DRAFT)->count();
    }

    /**
     * Put the starter products live, when the merchant ticked it at publish:
     * a shop published without them is a shop with nothing to buy. Only the
     * plan's own products, and only those still drafts.
     */
    public function activateStarters(Store $store): int
    {
        $ids = (array) data_get($store->settings, self::SAMPLES_SETTING, []);

        return $ids === [] ? 0 : $store->products()
            ->whereIn('id', $ids)
            ->where('status', Product::STATUS_DRAFT)
            ->update(['status' => Product::STATUS_ACTIVE]);
    }

    /**
     * The plan's example products, as drafts with photos.
     *
     * A second plan replaces the first one's examples rather than piling more
     * on — but only those still drafts: one the merchant has made active is
     * theirs now, whatever it started as.
     *
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function starterProducts(Store $store, array $rows): int
    {
        $previous = (array) data_get($store->settings, self::SAMPLES_SETTING, []);

        if ($previous !== []) {
            $store->products()->whereIn('id', $previous)->where('status', Product::STATUS_DRAFT)->get()->each->delete();
        }

        $ids = [];

        foreach (array_values($rows) as $i => $row) {
            $product = $store->products()->create([
                'name' => $row['name'],
                'slug' => Product::uniqueSlug($store->id, $row['name']),
                'description' => filled($row['description'] ?? null) ? '<p>' . e($row['description']) . '</p>' : null,
                'price' => $row['price'],
                'compare_at_price' => $row['compare'] ?? null,
                'track_stock' => false,
                'status' => Product::STATUS_DRAFT,
                'sort_order' => $i,
            ]);

            if (filled($row['category'] ?? null) && $category = $store->categories()->where('name', $row['category'])->first()) {
                $product->categories()->attach($category->id);
            }

            $product->images()->create([
                'path' => $this->photoFor($store, $product, $row),
                'alt' => $row['name'],
                'sort_order' => 0,
            ]);

            $ids[] = $product->id;
        }

        $store->update(['settings' => [...(array) $store->settings, self::SAMPLES_SETTING => $ids]]);

        return count($ids);
    }

    /**
     * The photo StarterPhotos fetched with the plan, moved in with the
     * product's own files; a drawn placeholder when there was none.
     *
     * @param  array<string,mixed>  $row
     */
    private function photoFor(Store $store, Product $product, array $row): string
    {
        $disk = Storage::disk('public');
        $fetched = (string) ($row['image'] ?? '');

        if ($fetched !== '' && $disk->exists($fetched)) {
            $path = "samples/{$store->id}/{$product->id}.jpg";
            $disk->move($fetched, $path);

            return $path;
        }

        return $this->artwork->store(['name' => $row['name'], 'hue' => crc32($row['name']) % 360], "samples/{$store->id}", $product->id);
    }

    /**
     * The platform's selling order — something to buy above the fold, then
     * discounts, then reasons to trust a shop you have never bought from — with
     * the shop's own story and questions after it.
     *
     * @return array<int,array<string,mixed>>
     */
    private function home(array $plan): array
    {
        $titles = (array) ($plan['titles'] ?? []);

        $sections = [
            $this->section('hero'),
            $this->section('deals', array_filter(['title' => $titles['deals'] ?? ''])),
            // Never the registry's default items: those promise a 2–5 day
            // delivery and a 14-day exchange this merchant may not offer.
            $this->section('trust_bar', ['items' => $plan['trust_items'] ?: [self::CASH_ON_DELIVERY]]),
            $this->section('categories', array_filter(['title' => $titles['categories'] ?? ''])),
            $this->section('product_grid', array_filter(['title' => $titles['products'] ?? '', 'source' => 'newest'])),
        ];

        if (filled($plan['story']['body'] ?? null)) {
            $sections[] = $this->section('rich_text', [
                'heading' => $plan['story']['heading'] ?? '',
                'body' => $plan['story']['body'],
            ]);
        }

        if (! empty($plan['faq'])) {
            $sections[] = $this->section('faq', ['items' => $plan['faq']]);
        }

        return $sections;
    }

    /** @return array<int,array<string,mixed>> */
    private function header(array $plan): array
    {
        return [
            // Same reason as the trust bar: the default line promises a
            // 14-day exchange.
            $this->section('announcement_bar', ['text' => $plan['announcement'] ?: 'الدفع عند الاستلام']),
            $this->section('header_nav'),
        ];
    }

    /**
     * @param  array<string,string>  $social
     * @return array<int,array<string,mixed>>
     */
    private function footer(array $plan, array $social): array
    {
        $sections = [$this->section('footer_main', ['about' => $plan['about'] ?? ''])];

        if ($social !== []) {
            $sections[] = $this->section('footer_social', ['items' => collect($social)
                ->map(fn (string $url, string $platform) => ['platform' => $platform, 'url' => $url])
                ->values()
                ->all()]);
        }

        $sections[] = $this->section('footer_note');

        return $sections;
    }

    /**
     * A section with the registry's defaults under whatever the plan says.
     *
     * @param  array<string,mixed>  $settings
     * @return array<string,mixed>
     */
    private function section(string $type, array $settings = []): array
    {
        return [
            'type' => $type,
            'visible' => true,
            'settings' => [...$this->registry->defaultSettings($type), ...$settings],
        ];
    }

    /**
     * The names the merchant kept, with the plan's description where the name
     * is still the one it wrote. Skips any the store already has, so applying
     * twice never doubles the menu.
     *
     * @param  array<int,string>  $names
     * @param  array<int,array{name:string,description:string}>  $suggested
     */
    private function createCategories(Store $store, array $names, array $suggested): int
    {
        $descriptions = collect($suggested)->pluck('description', 'name');
        $taken = $store->categories()->pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->all();
        $order = (int) $store->categories()->max('sort_order');
        $created = 0;

        foreach ($names as $name) {
            $name = trim($name);

            if ($name === '' || in_array(mb_strtolower($name), $taken, true)) {
                continue;
            }

            $taken[] = mb_strtolower($name);

            $store->categories()->create([
                'name' => $name,
                'description' => $descriptions->get($name),
                'slug' => Category::uniqueSlug($store->id, $name),
                'is_active' => true,
                'sort_order' => ++$order,
            ]);

            $created++;
        }

        return $created;
    }

    private const CASH_ON_DELIVERY = ['icon' => 'wallet', 'title' => 'الدفع عند الاستلام', 'body' => 'ادفع لما الأوردر يوصلك'];
}

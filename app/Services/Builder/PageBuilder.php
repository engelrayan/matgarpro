<?php

namespace App\Services\Builder;

use App\Models\Store;
use App\Models\StorePage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Loading, saving, publishing and reverting a page's layout.
 *
 * The one rule that runs through all of it: **the storefront reads
 * `published_sections`, the builder reads `draft_sections`, and the two are
 * never the same array.** A merchant dragging sections around at midday is
 * doing it to a copy; customers keep seeing the last thing that was published
 * until the moment they press نشر.
 */
class PageBuilder
{
    public function __construct(
        private readonly SectionRegistry $registry,
        private readonly SectionSanitizer $sanitizer,
    ) {}

    /**
     * What customers get: the published layout, or the platform's default for
     * a store that has never opened the builder.
     *
     * The fallback is why launching this feature cannot break a single
     * existing shop — a store with no row here renders exactly what it
     * rendered yesterday.
     *
     * @return array<int,array<string,mixed>>
     */
    public function published(Store $store, string $page): array
    {
        $row = $this->row($store, $page, create: false);

        return $row?->published_sections ?? $this->defaults($page);
    }

    /**
     * What the merchant is editing. Starts as a copy of what is live.
     *
     * @return array<int,array<string,mixed>>
     */
    public function draft(Store $store, string $page): array
    {
        $row = $this->row($store, $page, create: false);

        return $row?->draft_sections ?? $row?->published_sections ?? $this->defaults($page);
    }

    /** @param array<int,mixed> $sections */
    public function saveDraft(Store $store, string $page, array $sections): StorePage
    {
        $row = $this->row($store, $page, create: true);

        $row->draft_sections = $this->sanitizer->sections($store, $page, $sections);
        $row->save();

        return $row;
    }

    /** Draft → live, in one write. */
    public function publish(Store $store, string $page): StorePage
    {
        return DB::transaction(function () use ($store, $page) {
            $row = $this->row($store, $page, create: true);

            // A merchant who opened the builder and pressed نشر without
            // touching anything is publishing the defaults — which is a real
            // thing to want, and makes the layout theirs from then on.
            $row->published_sections = $row->draft_sections ?? $this->defaults($page);
            $row->draft_sections = $row->published_sections;
            $row->published_at = now();
            $row->save();

            return $row;
        });
    }

    /** Throw the editing session away and start again from what is live. */
    public function discardDraft(Store $store, string $page): StorePage
    {
        $row = $this->row($store, $page, create: true);

        $row->draft_sections = $row->published_sections ?? $this->defaults($page);
        $row->save();

        return $row;
    }

    /**
     * Put a page back to the platform's layout, draft side only — so it is
     * still a change the merchant has to publish, not one that happens to
     * their live shop the instant they press a button labelled "reset".
     */
    public function resetDraftToDefaults(Store $store, string $page): StorePage
    {
        $row = $this->row($store, $page, create: true);

        $row->draft_sections = $this->defaults($page);
        $row->save();

        return $row;
    }

    /**
     * The layout every store had before this feature existed.
     *
     * Written out section by section rather than generated, because it is a
     * design — the order here is the order that sells: something to buy above
     * the fold, then the discounts, then the reasons to trust a shop you have
     * never bought from.
     *
     * @return array<int,array<string,mixed>>
     */
    public function defaults(string $page): array
    {
        $sections = match (SectionRegistry::kindOf($page)) {
            'home' => ['hero', 'deals', 'trust_bar', 'categories', 'product_grid'],
            'product' => ['product_main', 'product_description', 'trust_bar', 'related_products'],
            'category' => ['category_header', 'category_products'],
            'header' => ['announcement_bar', 'header_nav'],
            'footer' => ['footer_main', 'footer_note'],
            /*
             | The order a one-product page sells in: the promise and the price
             | above the fold, the reasons, then the form while the reader is
             | still convinced — and only after it, for the ones who scroll on,
             | the proof, the guarantee and the answers. Empty parts render
             | nothing, so a fresh landing page is already the product's hero,
             | its form and its real orders until the merchant (or the AI)
             | fills the rest.
             */
            SectionRegistry::LANDING => [
                'landing_hero', 'trust_bar', 'benefits', 'product_main', 'live_orders', 'comparison', 'guarantee', 'faq',
            ],
            default => [],
        };

        // On a landing page the hero already shows the product, so the form
        // starts without a second copy of the same photos.
        $overrides = SectionRegistry::kindOf($page) === SectionRegistry::LANDING
            ? ['product_main' => ['gallery' => false]]
            : [];

        return array_map(fn (string $type) => [
            'id' => Str::lower(Str::random(12)),
            'type' => $type,
            'visible' => true,
            'settings' => [...$this->registry->defaultSettings($type), ...($overrides[$type] ?? [])],
        ], $sections);
    }

    /**
     * Every page's state, for the builder's page switcher.
     *
     * @return array<int,array<string,mixed>>
     */
    public function overview(Store $store): array
    {
        $rows = $store->pages()->get()->keyBy('key');

        return collect(SectionRegistry::PAGES)
            ->map(function (string $page) use ($rows) {
                // `->get()`, not `[$page]`: a Collection throws on a missing
                // key rather than returning null, and every page is missing
                // for a store that has not opened the builder yet — which is
                // every store, the first time.
                $row = $rows->get($page);

                return [
                    'key' => $page,
                    'label' => SectionRegistry::pageLabel($page),
                    'published' => $row?->published_at?->diffForHumans(),
                    'dirty' => (bool) $row?->hasUnpublishedChanges(),
                ];
            })
            ->all();
    }

    /**
     * Does this product have a landing page — published, or (for a preview)
     * at least started? The product URL serves it instead of the shared
     * product layout when it does.
     */
    public function hasLanding(Store $store, int $productId, bool $includeDrafts = false): bool
    {
        return StorePage::where('store_id', $store->id)
            ->where('key', SectionRegistry::landingKey($productId))
            ->when(! $includeDrafts, fn ($q) => $q->whereNotNull('published_sections'))
            ->exists();
    }

    /**
     * Throw a landing page away entirely; the product goes back to the shared
     * product layout on the very next request. Only landing pages: the store's
     * own pages fall back to defaults, which is what reset is for.
     */
    public function removeLanding(Store $store, int $productId): void
    {
        StorePage::where('store_id', $store->id)->where('key', SectionRegistry::landingKey($productId))->delete();
    }

    private function row(Store $store, string $page, bool $create): ?StorePage
    {
        abort_unless(SectionRegistry::isPage($page), 404);

        $query = StorePage::where('store_id', $store->id)->where('key', $page);

        return $create
            ? $query->firstOr(fn () => StorePage::create(['store_id' => $store->id, 'key' => $page]))
            : $query->first();
    }
}

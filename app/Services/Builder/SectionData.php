<?php

namespace App\Services\Builder;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Fetches what a page's sections need to render, once.
 *
 * The naive shape of a section system is "each section queries for itself",
 * and it produces a home page with a hero query, a deals query, a categories
 * query and then one query per hand-picked product — on a page bought with ad
 * money, where the merchant pays for the wait.
 *
 * So the whole list is inspected first: every hand-picked product id across
 * every section is fetched in one `whereIn`, the category list is fetched once
 * and shared with the nav and the footer, and only the genuinely different
 * queries (newest, discounted, one category) cost a round trip each.
 */
class SectionData
{
    private ?Collection $categories = null;

    /** @var Collection<int,Product>|null */
    private ?Collection $pickedProducts = null;

    /** @var array<string,array<string,mixed>> */
    private array $resolved = [];

    private ?LengthAwarePaginator $paginator = null;

    /**
     * @param  array<int,array<string,mixed>>  $sections
     * @param  array<string,mixed>  $context  product / category being viewed
     */
    public function __construct(
        private readonly Store $store,
        private readonly array $sections,
        private readonly array $context = [],
    ) {
        /*
         | Resolved up front, not lazily on first render.
         |
         | The controller needs the paginator — for the canonical link, and for
         | anything asserting on it — before Blade has run a single section, and
         | a lazily-filled property would still be null at that point. Hidden
         | sections are skipped, so switching a block off still costs nothing.
         */
        foreach ($sections as $section) {
            if ($section['visible'] ?? true) {
                $this->resolved[$section['id']] = $this->resolve($section);
            }
        }
    }

    /** @return array<string,mixed> */
    public function for(array $section): array
    {
        return $this->resolved[$section['id']] ??= $this->resolve($section);
    }

    /**
     * The page's paginated grid, if it has one.
     *
     * At most one section paginates: two paginators on a page would share the
     * `?page=` parameter and move together, which is worse than not paginating
     * at all.
     */
    public function paginator(): ?LengthAwarePaginator
    {
        return $this->paginator;
    }

    /**
     * Categories with something in them, shared by the nav, the footer and the
     * categories section. Fetched at most once per request.
     */
    public function categories(): Collection
    {
        return $this->categories ??= $this->store->categories()
            ->where('is_active', true)
            // An empty category is a dead end the customer paid attention to
            // reach, so it is never offered anywhere.
            ->has('products')
            ->withCount('products')
            ->with(['products' => fn ($q) => $q->where('status', Product::STATUS_ACTIVE)
                ->with('images')->orderBy('sort_order')->limit(1)])
            ->orderBy('sort_order')
            ->get();
    }

    /** @return array<string,mixed> */
    private function resolve(array $section): array
    {
        $settings = $section['settings'];

        return match ($section['type']) {
            'hero' => ['slides' => $this->heroSlides($settings)],
            'deals' => ['products' => $this->deals((int) ($settings['limit'] ?? 8))],
            'categories' => ['categories' => $this->categories()->take((int) ($settings['limit'] ?? 8))],
            'featured_products' => ['products' => $this->picked($settings['products'] ?? [])],
            'product_grid' => ['products' => $this->grid($settings)],
            'related_products' => ['products' => $this->related((int) ($settings['limit'] ?? 4))],
            default => [],
        };
    }

    /**
     * The hero either shows what the merchant uploaded, or builds itself from
     * the catalogue. The second is the default on purpose: a merchant who has
     * added products has a working hero without doing anything, and an empty
     * hero is the first thing a visitor sees.
     */
    private function heroSlides(array $settings): Collection
    {
        if (($settings['source'] ?? 'auto') === 'manual') {
            return collect($settings['slides'] ?? [])->filter(fn ($slide) => filled($slide['image'] ?? null));
        }

        return $this->activeProducts()
            ->has('images')
            ->orderByRaw('CASE WHEN compare_at_price IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->limit((int) ($settings['limit'] ?? 3))
            ->get();
    }

    private function deals(int $limit): Collection
    {
        return $this->activeProducts()
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            // An ended sale is not a deal. No deadline means it stays.
            ->where(fn ($q) => $q->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>', now()))
            ->orderByRaw('sale_ends_at IS NULL, sale_ends_at ASC')
            ->limit($limit)
            ->get();
    }

    private function grid(array $settings): Collection|LengthAwarePaginator
    {
        $perPage = (int) ($settings['limit'] ?? 24);
        $source = $settings['source'] ?? 'all';

        /*
         | "كل المنتجات" is the one grid that has to paginate. A shop with 200
         | products and a hard limit of 24 has 176 products no customer can
         | ever reach — which is what the old fixed home page paginated for,
         | and losing it would be a silent regression on every large store.
         */
        if ($source === 'all' && ($settings['paginate'] ?? true) && $this->paginator === null) {
            return $this->paginator = $this->activeProducts()->paginate($perPage);
        }

        $query = $this->activeProducts()->limit($perPage);

        return match ($source) {
            'newest' => $query->reorder()->latest('id')->get(),
            'discounted' => $query->whereNotNull('compare_at_price')
                ->whereColumn('compare_at_price', '>', 'price')->get(),
            'category' => $this->inCategory($query, $settings['category'] ?? []),
            default => $query->get(),
        };
    }

    private function inCategory($query, array $categoryIds): Collection
    {
        $id = $categoryIds[0] ?? null;

        if (! $id) {
            return collect();
        }

        return $query->whereHas('categories', fn ($q) => $q->where('categories.id', $id))->get();
    }

    /**
     * Products from the same category as the one being viewed — and never the
     * product itself, which reads as a bug the moment anyone notices it.
     */
    private function related(int $limit): Collection
    {
        $product = $this->context['product'] ?? null;

        if (! $product) {
            return collect();
        }

        $categoryIds = $product->categories->pluck('id');

        return $this->activeProducts()
            ->whereKeyNot($product->id)
            ->when($categoryIds->isNotEmpty(), fn ($q) => $q->whereHas(
                'categories',
                fn ($inner) => $inner->whereIn('categories.id', $categoryIds),
            ))
            ->limit($limit)
            ->get();
    }

    /**
     * Hand-picked products, in the merchant's order.
     *
     * Every id across every section on the page is fetched together the first
     * time any section asks, so three "منتجات مختارة" blocks cost one query,
     * not three.
     *
     * @param  array<int,int>  $ids
     */
    private function picked(array $ids): Collection
    {
        $this->pickedProducts ??= $this->loadAllPicked();

        return collect($ids)
            ->map(fn ($id) => $this->pickedProducts->get($id))
            ->filter()
            ->values();
    }

    private function loadAllPicked(): Collection
    {
        $ids = collect($this->sections)
            ->where('type', 'featured_products')
            ->flatMap(fn (array $section) => $section['settings']['products'] ?? [])
            ->unique()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return $this->activeProducts()->whereIn('id', $ids)->get()->keyBy('id');
    }

    private function activeProducts()
    {
        return $this->store->products()
            ->with(['images', 'variants'])
            ->where('status', Product::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }
}

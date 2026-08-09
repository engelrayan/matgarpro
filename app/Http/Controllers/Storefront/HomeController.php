<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Services\Builder\PageRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The home and category pages are now section lists, not fixed templates.
 *
 * What used to be four `private function`s fetching a hero, deals, categories
 * and products has moved into {@see \App\Services\Builder\SectionData}, which
 * fetches only what the merchant's arrangement actually asks for — a store
 * that deleted the deals block no longer pays for the deals query.
 *
 * Search keeps its own template: it is a result list, not something anybody
 * would want to lay out.
 */
class HomeController extends Controller
{
    public function __construct(private readonly PageRenderer $renderer) {}

    public function __invoke(Request $request, Store $store): View
    {
        $page = $this->renderer->page($request, $store, 'home');

        return view('storefront.home', [
            'store' => $store,
            // The paginating grid's paginator, hoisted so the page itself can
            // reason about "the product list" without knowing which section
            // produced it.
            'products' => $page['sectionData']->paginator(),
            ...$page,
        ]);
    }

    public function category(Request $request, Store $store, string $slug): View
    {
        $category = $store->categories()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $products = $category->products()
            ->with(['images', 'variants'])
            ->where('status', Product::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderByDesc('products.id')
            ->paginate(24);

        return view('storefront.category', [
            'store' => $store,
            'category' => $category,
            // The locked category sections read these directly rather than
            // through SectionData: the page is *about* this category, so its
            // products are context, not a section's own query.
            'products' => $products,
            ...$this->renderer->page($request, $store, 'category', ['category' => $category]),
        ]);
    }

    /**
     * Storefront search.
     *
     * Name and SKU only. A description search sounds generous and mostly
     * returns products that merely mention the word, which reads as a broken
     * search rather than a thorough one.
     */
    public function search(Request $request, Store $store): View
    {
        $term = trim($request->string('q')->toString());

        $products = $store->products()
            ->with(['images', 'variants'])
            ->where('status', Product::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->when($term !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%"),
            ))
            // An empty query returns nothing rather than the whole catalogue:
            // "0 results" for a blank box is confusing, so the view shows a
            // prompt instead.
            ->when($term === '', fn ($q) => $q->whereRaw('1 = 0'))
            ->paginate(24)
            ->withQueryString();

        return view('storefront.search', [
            'store' => $store,
            'term' => $term,
            'products' => $products,
            /*
             | Passed explicitly, even though the layout composer also provides
             | it: Blade renders a child view's sections BEFORE the layout, so
             | data the composer attaches to the layout does not exist yet when
             | `@section('content')` runs. The empty-results block lists
             | categories, and it runs there.
             */
            'navCategories' => $this->renderer->chrome($request, $store)['navCategories'],
        ]);
    }
}

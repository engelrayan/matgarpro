<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Products\DuplicateProduct;
use App\Actions\Products\SaveProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly SaveProduct $save,
        private readonly DuplicateProduct $duplicate,
    ) {}

    public function index(Request $request): Response
    {
        $store = $this->currentStore($request);

        $products = $store->products()
            ->with('images')
            // A product with variants keeps its own stock at 0 — the real
            // counts live on the variants. Summing here is what stops the list
            // marking a fully-stocked shirt as sold out.
            ->withCount('variants')
            ->withSum('variants', 'stock')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"),
            ))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->price,
                'compare_at_price' => $p->compare_at_price,
                'stock' => $p->variants_count > 0 ? (int) $p->variants_sum_stock : $p->stock,
                'variants_count' => $p->variants_count,
                'track_stock' => $p->track_stock,
                'status' => $p->status,
                'image' => $p->primaryImage()?->url(),
                'url' => $store->canonicalUrl() . '/p/' . $p->slug,
                'created_at' => $p->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('products/Index', [
            'products' => $products,
            'filters' => ['q' => $request->string('q')->toString()],
        ]);
    }

    public function create(Request $request): Response
    {
        $store = $this->currentStore($request);

        return Inertia::render('products/Form', [
            'product' => null,
            'currency' => $store->currency,
            // Drawn in the live preview's store bar, so the merchant sees their
            // own branding rather than a placeholder.
            'storeName' => $store->name,
            'settingDefaults' => config('products.defaults'),
            'categories' => $this->categoryOptions($store),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = $this->save->handle(
            $this->currentStore($request),
            $request->validated(),
            $request->file('images', []),
        );

        return to_route('products.edit', $product)->with('status', 'product-created');
    }

    public function edit(Request $request, Product $product): Response
    {
        $this->authorizeProduct($request, $product);

        $store = $this->currentStore($request);
        $product->load(['images', 'variants', 'categories']);

        return Inertia::render('products/Form', [
            'currency' => $store->currency,
            'storeName' => $store->name,
            'settingDefaults' => config('products.defaults'),
            'categories' => $this->categoryOptions($store),
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'price' => $product->price,
                'compare_at_price' => $product->compare_at_price,
                'cost' => $product->cost,
                'sku' => $product->sku,
                'track_stock' => $product->track_stock,
                'stock' => $product->stock,
                'status' => $product->status,
                'seo_title' => $product->seo_title,
                'seo_description' => $product->seo_description,
                'options' => $product->options ?? [],
                // Merged with the defaults, so a product saved before a toggle
                // existed still arrives with every key the form expects.
                'settings' => $product->pageSettings(),
                'categories' => $product->categories->pluck('id'),
                'url' => $store->canonicalUrl() . '/p/' . $product->slug,
                'images' => $product->images->map(fn ($i) => [
                    'id' => $i->id,
                    'url' => $i->url(),
                ]),
                'variants' => $product->variants->map(fn ($v) => [
                    'options' => $v->options,
                    'price' => $v->price,
                    'stock' => $v->stock,
                    'sku' => $v->sku,
                ]),
            ],
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        $this->save->handle(
            $this->currentStore($request),
            $request->validated(),
            $request->file('images', []),
            $product,
        );

        return back()->with('status', 'product-saved');
    }

    public function replicate(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        $copy = $this->duplicate->handle($product);

        return to_route('products.edit', $copy)->with('status', 'product-duplicated');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);

        $product->delete();

        return to_route('products.index')->with('status', 'product-deleted');
    }

    /**
     * Categories the merchant can file this product under.
     *
     * Includes hidden ones: a merchant staging a section before launch still
     * needs to put products in it.
     *
     * @return array<int,array<string,mixed>>
     */
    private function categoryOptions(Store $store): array
    {
        return $store->categories()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'is_active'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'is_active' => $c->is_active])
            ->all();
    }

    private function currentStore(Request $request): Store
    {
        return $request->user()->currentStore();
    }

    /** A merchant may only touch products in a store they own. */
    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless(
            $request->user()->stores()->whereKey($product->store_id)->exists(),
            403,
        );
    }
}

<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\Builder\LandingTemplates;
use App\Services\Builder\PageBuilder;
use App\Services\Builder\PreviewToken;
use App\Services\Builder\SectionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store builder.
 *
 * Every write goes through {@see PageBuilder}, which puts the posted layout
 * back together from the section schema before it touches the database — so
 * this controller never has to trust what arrived, and there is no path where
 * a hand-made request reaches a blade with a field nobody validated.
 *
 * Nothing here writes `published_sections` except `publish`. That is the whole
 * safety story of the feature: a merchant can do anything they like in here at
 * midday and their live shop does not change until they say so.
 */
class BuilderController extends Controller
{
    public function __construct(
        private readonly PageBuilder $builder,
        private readonly SectionRegistry $registry,
        private readonly PreviewToken $tokens,
    ) {}

    public function index(Request $request, string $page = 'home'): Response
    {
        $store = $request->user()->currentStore();
        $product = $this->guardPage($store, $page);

        return Inertia::render('builder/Index', [
            'page' => $page,
            'pageLabel' => SectionRegistry::pageLabel($page),
            // Set only on a product's landing page: which product, and whether
            // its page is live — the header says so, and offers to take it down.
            'landing' => $product ? [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_url' => $store->canonicalUrl() . '/p/' . $product->slug,
                'live' => $this->builder->hasLanding($store, $product->id),
                // Not started yet: the builder opens on the template chooser
                // rather than on a page the merchant did not pick.
                'started' => $this->builder->hasLanding($store, $product->id, includeDrafts: true),
                // A draft product's page can be designed and previewed, but no
                // customer reaches it until the product itself is published.
                'product_active' => $product->status === Product::STATUS_ACTIVE,
                'ai_available' => filled(config('services.anthropic.key')),
                'ai_price' => (float) config('billing.ai_landing_page_price'),
                'templates' => app(LandingTemplates::class)->catalogue(),
            ] : null,
            'pages' => $this->builder->overview($store),
            'sections' => $this->builder->draft($store, $page),
            // The catalogue for "أضف قسم", already filtered to what may live
            // on this page — offering a buy form on the footer and refusing it
            // on save is a worse experience than not offering it.
            'catalogue' => $this->registry->forPage($page),
            'previewUrl' => $this->tokens->urlFor($store, $page, $this->tokens->issue($store)),
            'storeUrl' => $store->canonicalUrl(),
            'currency' => $store->currency,
            'categories' => $store->categories()->orderBy('sort_order')
                ->get(['id', 'name'])
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name]),
        ]);
    }

    /** Save the draft. Called on every change, debounced by the client. */
    public function update(Request $request, string $page): RedirectResponse
    {
        $store = $request->user()->currentStore();
        $this->guardPage($store, $page);

        $this->builder->saveDraft($store, $page, (array) $request->input('sections', []));

        return back(303);
    }

    public function publish(Request $request, string $page): RedirectResponse
    {
        $store = $request->user()->currentStore();
        $this->guardPage($store, $page);

        $this->builder->publish($store, $page);

        return back()->with('status', 'builder-published');
    }

    public function discard(Request $request, string $page): RedirectResponse
    {
        $store = $request->user()->currentStore();
        $this->guardPage($store, $page);

        $this->builder->discardDraft($store, $page);

        return back()->with('status', 'builder-discarded');
    }

    /** Back to the platform's layout — draft side only, so it is still a change. */
    public function reset(Request $request, string $page): RedirectResponse
    {
        $store = $request->user()->currentStore();
        $this->guardPage($store, $page);

        $this->builder->resetDraftToDefaults($store, $page);

        return back()->with('status', 'builder-reset');
    }

    /**
     * Start a landing page from one of the ready-made templates — into the
     * draft, like everything else here, so the live page is untouched.
     */
    public function template(Request $request, string $page, LandingTemplates $templates): RedirectResponse
    {
        $store = $request->user()->currentStore();
        abort_unless($this->guardPage($store, $page) !== null, 404);

        $key = $request->string('template')->toString();
        abort_unless($templates->has($key), 422);

        $this->builder->saveDraft($store, $page, $templates->sections($key));

        return back()->with('status', 'builder-template');
    }

    /**
     * Take a product's landing page down. The product URL goes back to the
     * shared product layout on the next request; ads keep working throughout.
     */
    public function destroyLanding(Request $request, Product $product): RedirectResponse
    {
        $store = $request->user()->currentStore();
        abort_unless((int) $product->store_id === (int) $store->id, 404);

        $this->builder->removeLanding($store, $product->id);

        return to_route('products.edit', $product)->with('status', 'landing-removed');
    }

    /**
     * A page key must be one this builder knows, and a landing page must be
     * for one of the merchant's own products — the key carries a product id,
     * and nothing else stops it naming somebody else's.
     */
    private function guardPage(Store $store, string $page): ?Product
    {
        abort_unless(SectionRegistry::isPage($page), 404);

        $productId = SectionRegistry::landingProductId($page);

        if ($productId === null) {
            return null;
        }

        return $store->products()->findOrFail($productId);
    }

    /**
     * Products for the picker.
     *
     * A search endpoint rather than shipping the whole catalogue into the page:
     * a shop with two thousand products would otherwise pay for all of them on
     * every builder load, to let the merchant choose four.
     */
    public function products(Request $request): JsonResponse
    {
        $store = $request->user()->currentStore();
        $term = trim($request->string('q')->toString());
        $ids = array_filter((array) $request->input('ids', []), 'is_numeric');

        $products = $store->products()
            ->with('images')
            // `ids` is how the picker resolves what is already selected, by id,
            // regardless of whether it matches the current search.
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->when($ids === [] && $term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy('sort_order')
            ->limit(40)
            ->get();

        return response()->json(
            $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'image' => $product->primaryImage()?->url(),
            ])
        );
    }

    /**
     * An image for a banner, a slide, a logo strip.
     *
     * Stored under the store's own folder and returned as a path, never a URL:
     * the sanitiser only accepts paths beginning `builder/`, so a value that
     * did not come from here cannot be saved into a section.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ], [
            'image.max' => 'الصورة كبيرة أوي — أقصى حجم ٤ ميجا.',
            'image.image' => 'الملف ده مش صورة.',
        ]);

        $store = $request->user()->currentStore();

        $path = $request->file('image')->store('builder/' . $store->id, 'public');

        return response()->json(['path' => $path, 'url' => \App\Support\Media::url($path)]);
    }
}

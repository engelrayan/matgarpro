<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Services\Builder\PageBuilder;
use App\Services\Builder\PageRenderer;
use App\Services\Builder\SectionRegistry;
use App\Services\Storefront\FunnelTracker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductController extends Controller
{
    public function __construct(
        private readonly FunnelTracker $funnel,
        private readonly PageRenderer $renderer,
        private readonly PageBuilder $builder,
    ) {}

    public function __invoke(Request $request, Store $store, string $slug): View
    {
        /*
         | The builder's preview sees drafts too. A merchant designs a landing
         | page *before* putting the product live — and a duplicated product
         | always starts as a draft — so a preview that 404s on drafts is a
         | builder that cannot be used for the very product being prepared.
         | The preview token is checked by BuilderPreview against this store.
         */
        $preview = $this->renderer->isPreview($request);

        $product = $store->products()
            ->with(['images', 'variants'])
            ->where('slug', $slug)
            ->when(! $preview, fn ($q) => $q->where('status', Product::STATUS_ACTIVE))
            ->first();

        // `isHidden()` also covers the merchant's "hide when out of stock"
        // setting, which status alone cannot express.
        if (! $product || (! $preview && $product->isHidden())) {
            throw new NotFoundHttpException();
        }

        // After the 404 check: a view of a page that does not exist is not a
        // view, and counting it would let a bot crawling bad URLs inflate the
        // merchant's traffic. Nor is the merchant looking at their own draft.
        if (! $preview) {
            $this->funnel->view($request, $store, $product);
        }

        $settings = $product->pageSettings();

        /*
         | Its own landing page, when the merchant has published one — on the
         | same URL, so the ads already running on it start selling with the
         | new page the moment it goes live, and stop the moment it is removed.
         |
         | A builder preview asks for it explicitly (`_landing`), because the
         | same product URL also previews the shared product layout.
         */
        $useLanding = $this->renderer->isPreview($request)
            ? $request->boolean('_landing')
            : $this->builder->hasLanding($store, $product->id);

        $context = ['product' => $product];
        $landingKey = SectionRegistry::landingKey($product->id);

        $page = match (true) {
            ! $useLanding => $this->renderer->page($request, $store, 'product', $context),
            // "Under the product page": the usual page first, then the landing
            // page's own parts — without its form, which the product page
            // already has, and two forms on one page is two different totals.
            (bool) $settings['landing_below_product'] => $this->renderer->combined($request, $store, ['product', $landingKey], $context, except: ['product_main']),
            default => $this->renderer->page($request, $store, $landingKey, $context),
        };

        return view('storefront.product', [
            /*
             | Named `productSettings`, not `settings`: the section blades all
             | receive their own `$settings`, and two different meanings for one
             | variable name inside the same render is how a buy button ends up
             | labelled with a banner's headline.
             */
            'productSettings' => $settings,
            // Whether a "وصف المنتج" section is on the page decides where the
            // description is rendered — the main block skips it rather than
            // showing the same paragraph twice.
            'hasDescriptionSection' => collect($page['sections'])
                ->contains(fn (array $section) => $section['type'] === 'product_description' && $section['visible']),
            'hideHeader' => (bool) $settings['hide_header'],
            'store' => $store,
            'product' => $product,
            ...$page,
            // Keyed by the same canonical string the client builds from the
            // selectors, so picking options is a map lookup instead of a search.
            'offers' => $offers = $product->offers()->get(),
            // What the page script needs to price an offer: the same figures
            // PlaceOrder charges, from the same rows.
            'offerMap' => $offers->mapWithKeys(fn ($o) => [
                $o->id => [
                    'quantity' => $o->quantity,
                    'unit' => (float) $o->unit_price,
                    'free_shipping' => $o->free_shipping,
                ],
            ]),
            'variantMap' => $product->variants->mapWithKeys(fn ($v) => [
                $v->key() => [
                    'id' => $v->id,
                    'price' => (float) $v->effectivePrice($product),
                    'stock' => $v->stock,
                ],
            ]),
        ]);
    }
}

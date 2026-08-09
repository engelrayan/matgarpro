<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Services\Builder\PageRenderer;
use App\Services\Storefront\FunnelTracker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductController extends Controller
{
    public function __construct(
        private readonly FunnelTracker $funnel,
        private readonly PageRenderer $renderer,
    ) {}

    public function __invoke(Request $request, Store $store, string $slug): View
    {
        $product = $store->products()
            ->with(['images', 'variants'])
            ->where('slug', $slug)
            ->where('status', Product::STATUS_ACTIVE)
            ->first();

        // `isHidden()` also covers the merchant's "hide when out of stock"
        // setting, which status alone cannot express.
        if (! $product || $product->isHidden()) {
            throw new NotFoundHttpException();
        }

        // After the 404 check: a view of a page that does not exist is not a
        // view, and counting it would let a bot crawling bad URLs inflate the
        // merchant's traffic.
        $this->funnel->view($request, $store, $product);

        $settings = $product->pageSettings();

        $page = $this->renderer->page($request, $store, 'product', ['product' => $product]);

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

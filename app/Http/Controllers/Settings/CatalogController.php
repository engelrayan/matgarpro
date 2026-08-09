<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function edit(Request $request): Response
    {
        $store = $request->user()->currentStore();

        return Inertia::render('settings/Catalog', [
            'feeds' => [
                'meta' => $store->canonicalUrl() . '/feed/meta.xml',
                'google' => $store->canonicalUrl() . '/feed/google.xml',
                'tiktok' => $store->canonicalUrl() . '/feed/tiktok.xml',
            ],
            /*
             | What the feed will actually contain, checked before the merchant
             | hands the link to Meta.
             |
             | The platforms reject silently-ish — a rejected item just never
             | appears, and the merchant spends a week wondering why their
             | catalogue is half empty. Saying it here turns that into a fix
             | they can make in five minutes.
             */
            'readiness' => $this->readiness($store),
        ]);
    }

    /** @return array<string,mixed> */
    private function readiness(Store $store): array
    {
        $active = $store->products()->where('status', Product::STATUS_ACTIVE);

        $total = (clone $active)->count();
        $withImages = (clone $active)->has('images')->count();

        // Every platform rejects an item with no picture, and SVG is not an
        // accepted format on any of them — the sample artwork we generate is
        // SVG, so a store still running on it would publish nothing.
        $svgOnly = (clone $active)
            ->whereHas('images', fn ($q) => $q->where('path', 'like', '%.svg'))
            ->count();

        $noDescription = (clone $active)->where(fn ($q) => $q->whereNull('description')->orWhere('description', ''))->count();

        return [
            'total' => $total,
            'ready' => max(0, $withImages - $svgOnly),
            'issues' => array_values(array_filter([
                $total === 0 ? [
                    'level' => 'error',
                    'text' => 'مفيش منتجات منشورة — الكاتالوج هيطلع فاضي.',
                    'action' => ['label' => 'أضف منتج', 'url' => '/products/create'],
                ] : null,

                $total > 0 && $withImages < $total ? [
                    'level' => 'error',
                    'text' => ($total - $withImages) . ' منتج من غير صورة — المنصات بترفض أي منتج من غير صورة.',
                    'action' => ['label' => 'المنتجات', 'url' => '/products'],
                ] : null,

                $svgOnly > 0 ? [
                    'level' => 'error',
                    'text' => $svgOnly . ' منتج صورته SVG — لازم JPG أو PNG، فيسبوك وجوجل مابيقبلوش SVG.',
                    'action' => ['label' => 'المنتجات', 'url' => '/products'],
                ] : null,

                $noDescription > 0 ? [
                    'level' => 'warning',
                    'text' => $noDescription . ' منتج من غير وصف — هيتقبل، بس الوصف بيحسّن ظهوره.',
                    'action' => ['label' => 'المنتجات', 'url' => '/products'],
                ] : null,
            ])),
        ];
    }
}

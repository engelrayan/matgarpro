<?php

namespace App\Services\Builder;

use App\Models\Store;
use Illuminate\Http\Request;

/**
 * The bridge between a stored layout and a rendered page.
 *
 * Held as a singleton for the life of the request so the header, the footer and
 * the page body share one {@see SectionData} — otherwise the category list is
 * fetched three times on a page that shows it in the nav, the grid and the
 * footer.
 *
 * It is also the only place that decides **which** layout is being rendered:
 * the published one for customers, the draft one for a preview. Every other
 * caller just asks for "the sections", which is what keeps a half-finished
 * draft from leaking onto a live storefront.
 */
class PageRenderer
{
    /** Set by {@see \App\Http\Middleware\BuilderPreview} on a valid signed URL. */
    public const PREVIEW_ATTRIBUTE = 'builder_preview';

    private ?SectionData $chromeData = null;

    public function __construct(private readonly PageBuilder $builder) {}

    public function isPreview(Request $request): bool
    {
        return (bool) $request->attributes->get(self::PREVIEW_ATTRIBUTE);
    }

    /**
     * A page's sections plus the data they need.
     *
     * @param  array<string,mixed>  $context
     * @return array{sections: array<int,array<string,mixed>>, sectionData: SectionData}
     */
    public function page(Request $request, Store $store, string $key, array $context = []): array
    {
        $sections = $this->isPreview($request)
            ? $this->builder->draft($store, $key)
            : $this->builder->published($store, $key);

        return [
            'sections' => $sections,
            'sectionData' => new SectionData($store, $sections, $context),
        ];
    }

    /**
     * Header and footer, shared by every storefront page.
     *
     * @return array<string,mixed>
     */
    public function chrome(Request $request, Store $store): array
    {
        $header = $this->isPreview($request)
            ? $this->builder->draft($store, 'header')
            : $this->builder->published($store, 'header');

        $footer = $this->isPreview($request)
            ? $this->builder->draft($store, 'footer')
            : $this->builder->published($store, 'footer');

        $this->chromeData ??= new SectionData($store, [...$header, ...$footer]);

        return [
            'headerSections' => $header,
            'footerSections' => $footer,
            'chromeData' => $this->chromeData,
            // The nav and the footer both list categories, and the section
            // blades read this rather than querying for themselves.
            'navCategories' => $this->chromeData->categories(),
        ];
    }
}

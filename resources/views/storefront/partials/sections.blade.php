{{-- Renders a page's configured section list, in order.

     The wrapper div is not decoration: `data-section-id` is what the builder's
     preview uses to outline a section on hover and select it on click. It is
     emitted always, not only in preview mode — a wrapper that exists in one
     mode and not the other means the merchant is arranging a page whose DOM is
     not the page customers get.

     Hidden sections are not rendered at all rather than rendered with
     `display:none`. A customer's browser should never download a block the
     merchant switched off, and the builder's own list is where "hidden" is
     visible. --}}
@foreach ($sections as $section)
    @continue(! ($section['visible'] ?? true))

    <div data-section-id="{{ $section['id'] }}" data-section-type="{{ $section['type'] }}">
        @include('storefront.sections.' . $section['type'], [
            'settings' => $section['settings'],
            'data' => $sectionData->for($section),
        ])
    </div>
@endforeach

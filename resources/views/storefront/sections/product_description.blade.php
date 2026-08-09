{{-- The description as its own block, for merchants who put the order form
     first and want the pitch underneath it.

     Renders nothing when the product page settings already show the
     description inline next to the price — otherwise the customer reads the
     same paragraph twice and the page looks like a bug. --}}
@if ($product->description && $productSettings['form_before_description'])
<section class="px-5 py-8">
    <div class="mx-auto max-w-3xl">
        @if ($settings['heading'])
            <h2 class="mb-4 text-lg font-bold tracking-tight">{{ $settings['heading'] }}</h2>
        @endif

        {{-- Unescaped: stored already sanitised by HtmlSanitizer. --}}
        <div class="prose-description text-sm leading-relaxed text-muted-foreground">
            {!! $product->description !!}
        </div>
    </div>
</section>
@endif

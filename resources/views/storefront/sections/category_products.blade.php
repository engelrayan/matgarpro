{{-- The paginated products of the category being viewed. --}}
<section class="mx-auto max-w-6xl px-5 py-8">
    @if ($products->isEmpty())
        <p class="py-20 text-center text-muted-foreground">مفيش منتجات في القسم ده دلوقتي.</p>
    @else
        <div @class([
            'grid gap-4',
            'grid-cols-2' => $settings['columns'] === '2',
            'grid-cols-2 md:grid-cols-3' => $settings['columns'] === '3',
            'grid-cols-2 md:grid-cols-3 lg:grid-cols-4' => $settings['columns'] === '4',
        ])>
            @foreach ($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @endforeach
        </div>

        <div class="mt-10">{{ $products->links() }}</div>
    @endif
</section>

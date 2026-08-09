{{-- Something else from the same section, so a customer who is not convinced
     by this product leaves with a different one instead of leaving. --}}
@php($products = $data['products'])

@if ($products->isNotEmpty())
<section class="border-t border-border px-5 py-10">
    <div class="mx-auto max-w-5xl">
        <h2 class="mb-5 text-lg font-bold tracking-tight">{{ $settings['title'] }}</h2>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach ($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
</section>
@endif

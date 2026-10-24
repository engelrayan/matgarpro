{{-- Something else from the same section, so a customer who is not convinced
     by this product leaves with a different one instead of leaving. --}}
@php($products = $data['products'])

@if ($products->isNotEmpty())
<section class="hairline-t px-4 py-8 md:px-5 md:py-10">
    <div class="mx-auto max-w-5xl">
        <h2 class="mb-4 text-[20px] font-bold leading-snug md:text-[22px]">{{ $settings['title'] }}</h2>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4 md:gap-5">
            @foreach ($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
</section>
@endif

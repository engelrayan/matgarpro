{{-- Hand-picked products, in the merchant's own order. --}}
@php($products = $data['products'])

@if ($products->isNotEmpty())
<section class="mx-auto max-w-6xl px-5 py-10">
    @if ($settings['title'])
        <h2 class="mb-5 text-lg font-bold tracking-tight">{{ $settings['title'] }}</h2>
    @endif

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
</section>
@endif

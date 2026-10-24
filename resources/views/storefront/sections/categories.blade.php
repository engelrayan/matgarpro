@php($items = $data['categories'])

@if ($items->isNotEmpty())
<section class="mx-auto max-w-6xl px-4 py-8 md:px-5 md:py-10">
    <h2 class="mb-4 text-[20px] font-bold leading-snug md:text-[22px]">{{ $settings['title'] }}</h2>

    <div @class([
        'grid gap-3',
        'grid-cols-2' => $settings['columns'] === '2',
        'grid-cols-2 sm:grid-cols-3' => $settings['columns'] === '3',
        'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4' => $settings['columns'] === '4',
    ])>
        @foreach ($items as $category)
            <a href="{{ route('storefront.category', $category->slug) }}"
               class="ios-group pressable group block">
                {{-- Falls back to the first product in the section. A merchant
                     rarely uploads a category image, and an empty grey panel
                     makes a stocked shop look broken. --}}
                <div class="aspect-[3/2] bg-muted">
                    @php($image = $category->imageUrl() ?? $category->products->first()?->primaryImage()?->url())

                    @if ($image)
                        <img src="{{ $image }}" alt="" loading="lazy"
                             class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.04]">
                    @endif
                </div>
                <div class="px-3 pb-3 pt-2.5">
                    <p class="text-[15px] font-semibold">{{ $category->name }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ $category->products_count }} منتج</p>
                </div>
            </a>
        @endforeach
    </div>
</section>
@endif

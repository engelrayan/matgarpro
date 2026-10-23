<section class="mx-auto max-w-6xl px-4 pt-5 md:px-5 md:pt-10">
    <nav class="mb-2 flex items-center gap-1.5 text-[15px] text-muted-foreground" aria-label="مسار الصفحة">
        <a href="{{ route('storefront.home') }}" class="text-primary hover:opacity-80">المتجر</a>
        <span aria-hidden="true">‹</span>
        <span class="text-foreground">{{ $category->name }}</span>
    </nav>

    <h1 class="text-large-title">{{ $category->name }}</h1>

    @if ($settings['show_description'] && $category->description)
        <p class="mt-2 max-w-2xl text-callout text-muted-foreground">{{ $category->description }}</p>
    @endif

    @if ($settings['show_count'])
        <p class="mt-1 text-[15px] text-muted-foreground"><span class="tabular">{{ $products->total() }}</span> منتج</p>
    @endif
</section>

<section class="mx-auto max-w-6xl px-5 pt-10">
    <nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground">
        <a href="{{ route('storefront.home') }}" class="hover:text-foreground">المتجر</a>
        <span>›</span>
        <span class="text-foreground">{{ $category->name }}</span>
    </nav>

    <h1 class="text-2xl font-bold tracking-tight md:text-3xl">{{ $category->name }}</h1>

    @if ($settings['show_description'] && $category->description)
        <p class="mt-3 max-w-2xl text-muted-foreground">{{ $category->description }}</p>
    @endif

    @if ($settings['show_count'])
        <p class="mt-2 text-sm text-muted-foreground">{{ $products->total() }} منتج</p>
    @endif
</section>

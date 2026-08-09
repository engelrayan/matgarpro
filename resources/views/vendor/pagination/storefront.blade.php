{{-- Storefront paginator.

     Laravel's bundled Tailwind view hardcodes its own palette and left/right
     margins, so it ignores the merchant's theme and its arrows point the wrong
     way in RTL. This one is drawn from the theme's own tokens. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="تنقّل بين الصفحات" class="flex flex-wrap items-center justify-center gap-1.5">

        @if ($paginator->onFirstPage())
            <span class="cursor-default rounded-[--radius] border border-border px-3 py-2 text-sm text-muted-foreground/40">السابق</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
               class="rounded-[--radius] border border-border px-3 py-2 text-sm transition-colors hover:border-primary hover:text-primary">السابق</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-2 text-sm text-muted-foreground">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page"
                              class="tabular min-w-10 rounded-[--radius] bg-primary px-3 py-2 text-center text-sm font-semibold text-primary-foreground">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}"
                           class="tabular min-w-10 rounded-[--radius] border border-border px-3 py-2 text-center text-sm transition-colors hover:border-primary hover:text-primary">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next"
               class="rounded-[--radius] border border-border px-3 py-2 text-sm transition-colors hover:border-primary hover:text-primary">التالي</a>
        @else
            <span class="cursor-default rounded-[--radius] border border-border px-3 py-2 text-sm text-muted-foreground/40">التالي</span>
        @endif
    </nav>
@endif

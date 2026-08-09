@extends('storefront.layout')

@section('title', $term ? "نتائج البحث عن «{$term}» — {$store->name}" : "بحث — {$store->name}")
{{-- Search pages are thin, duplicated and endless. Keeping them out of the
     index protects the pages that actually sell. --}}
@push('head')
    <meta name="robots" content="noindex,follow">
@endpush

@section('content')
<div class="mx-auto max-w-5xl px-5 py-10">

    @if ($term === '')
        <div class="py-20 text-center">
            <p class="text-lg font-medium">دوّر على اللي إنت عايزه</p>
            <p class="mt-2 text-sm text-muted-foreground">اكتب اسم المنتج في خانة البحث فوق.</p>
        </div>
    @else
        <h1 class="text-xl font-bold tracking-tight">
            نتائج البحث عن «{{ $term }}»
        </h1>
        <p class="mt-1 text-sm text-muted-foreground">
            <span class="tabular">{{ $products->total() }}</span> منتج
        </p>

        @if ($products->isEmpty())
            <div class="mt-12 rounded-[--radius] border border-border bg-muted/30 px-6 py-16 text-center">
                <p class="font-medium">مفيش نتائج لـ «{{ $term }}»</p>
                <p class="mx-auto mt-2 max-w-sm text-sm text-muted-foreground">
                    جرّب كلمة أقصر أو اتفرّج على الأقسام.
                </p>

                {{-- A dead end with nowhere to go is a lost customer. --}}
                @if ($navCategories->isNotEmpty())
                    <div class="mt-6 flex flex-wrap justify-center gap-2">
                        @foreach ($navCategories->take(6) as $category)
                            <a href="{{ route('storefront.category', $category->slug) }}"
                               class="rounded-[--radius] border border-border bg-card px-3 py-1.5 text-sm transition-colors hover:border-primary">
                                {{ $category->name }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    @include('storefront.partials.product-card')
                @endforeach
            </div>

            <div class="mt-10">
                {{ $products->links() }}
            </div>
        @endif
    @endif
</div>
@endsection

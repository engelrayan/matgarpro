@php($about = $settings['about'] ?: $store->description)

<div class="mx-auto max-w-5xl px-4 pt-10 md:px-5 md:pt-12">
    <div class="grid gap-8 sm:grid-cols-2 md:grid-cols-4">
        <div class="sm:col-span-2 md:col-span-1">
            <div class="flex items-center gap-2.5">
                @if ($logo = $store->logoUrl())
                    <img src="{{ $logo }}" alt="" class="size-9 rounded-[--radius] object-cover">
                @else
                    <span class="flex size-9 items-center justify-center rounded-[--radius] bg-primary text-base font-bold text-primary-foreground">
                        {{ mb_substr($store->name, 0, 1) }}
                    </span>
                @endif
                <span class="font-bold tracking-tight">{{ $store->name }}</span>
            </div>

            @if ($about)
                <p class="mt-3 text-sm leading-relaxed text-muted-foreground">{{ $about }}</p>
            @endif
        </div>

        @if ($settings['show_categories'] && $navCategories->isNotEmpty())
            <div>
                <p class="text-[15px] font-semibold">الأقسام</p>
                <ul class="mt-2 text-[15px] text-muted-foreground">
                    @foreach ($navCategories->take(5) as $category)
                        <li>
                            <a href="{{ route('storefront.category', $category->slug) }}"
                               class="flex min-h-[40px] items-center transition-colors hover:text-foreground">{{ $category->name }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @foreach (collect($settings['columns'])->filter(fn ($c) => filled($c['title'])) as $column)
            <div>
                <p class="text-[15px] font-semibold">{{ $column['title'] }}</p>
                <ul class="mt-2 text-[15px] text-muted-foreground">
                    @foreach (collect($column['links'])->filter(fn ($l) => filled($l['label'])) as $link)
                        <li>
                            <a href="{{ $link['url'] ?: '#' }}" class="flex min-h-[40px] items-center transition-colors hover:text-foreground">
                                {{ $link['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</div>

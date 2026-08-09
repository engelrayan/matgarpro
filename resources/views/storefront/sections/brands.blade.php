@php($items = collect($settings['items'])->filter(fn ($i) => filled($i['image'])))

@if ($items->isNotEmpty())
<section class="px-5 py-8">
    <div class="mx-auto max-w-5xl">
        @if ($settings['title'])
            <p class="mb-5 text-center text-sm text-muted-foreground">{{ $settings['title'] }}</p>
        @endif

        <div class="flex flex-wrap items-center justify-center gap-x-10 gap-y-6">
            @foreach ($items as $item)
                <{{ $item['link'] ? 'a' : 'div' }}
                    @if ($item['link']) href="{{ $item['link'] }}" rel="noopener" @endif
                    class="shrink-0">
                    {{-- Greyscale by default: a row of full-colour third-party
                         logos out-shouts the merchant's own products, which are
                         the only things on the page anyone can buy. --}}
                    <img src="{{ \App\Support\Media::url($item['image']) }}"
                         alt="{{ $item['name'] }}" loading="lazy"
                         class="h-8 w-auto object-contain transition {{ $settings['grayscale'] ? 'opacity-60 grayscale hover:opacity-100 hover:grayscale-0' : '' }}">
                </{{ $item['link'] ? 'a' : 'div' }}>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- One wide image, or two side by side. Each with its own link. --}}
@php
    $items = collect($settings['items'])->filter(fn ($item) => filled($item['image']))
        ->take($settings['layout'] === 'split' ? 2 : 1);
@endphp

@if ($items->isNotEmpty())
<section class="mx-auto max-w-6xl px-5 py-6">
    <div @class([
        'grid gap-4',
        'sm:grid-cols-2' => $settings['layout'] === 'split',
    ])>
        @foreach ($items as $item)
            {{-- A banner with no link is a div, not a dead <a> — an anchor that
                 goes nowhere still shows a hand cursor and still costs a tap. --}}
            <{{ $item['link'] ? 'a' : 'div' }}
                @if ($item['link']) href="{{ $item['link'] }}" @endif
                class="group relative block overflow-hidden rounded-[--radius]">

                <img src="{{ \App\Support\Media::url($item['image']) }}"
                     alt="{{ $item['headline'] }}"
                     loading="lazy"
                     class="w-full object-cover transition-transform duration-500 group-hover:scale-[1.03] {{ $settings['layout'] === 'split' ? 'aspect-[4/3]' : 'aspect-[16/6]' }}">

                @if ($item['headline'] || $item['sub'] || $item['button_text'])
                    <div class="absolute inset-0 flex items-center bg-gradient-to-l from-black/55 to-transparent p-6 md:p-10">
                        <div class="max-w-sm text-white">
                            @if ($item['headline'])
                                <p class="text-xl font-bold leading-snug drop-shadow md:text-2xl">{{ $item['headline'] }}</p>
                            @endif
                            @if ($item['sub'])
                                <p class="mt-1.5 text-sm drop-shadow">{{ $item['sub'] }}</p>
                            @endif
                            @if ($item['button_text'])
                                <span class="btn-primary mt-4">{{ $item['button_text'] }}</span>
                            @endif
                        </div>
                    </div>
                @endif
            </{{ $item['link'] ? 'a' : 'div' }}>
        @endforeach
    </div>
</section>
@endif

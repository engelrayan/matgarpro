@php($items = collect($settings['items'])->filter(fn ($i) => filled($i['text'])))

@if ($items->isNotEmpty())
<section class="border-y border-border bg-muted/30 px-5 py-12">
    <div class="mx-auto max-w-5xl">
        <h2 class="mb-6 text-center text-lg font-bold tracking-tight">{{ $settings['title'] }}</h2>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $item)
                <figure class="rounded-[--radius] border border-border bg-card p-5">
                    <div class="flex gap-0.5 text-gold-500" aria-label="{{ $item['rating'] }} من ٥">
                        @for ($i = 0; $i < 5; $i++)
                            <svg class="size-4 {{ $i < $item['rating'] ? 'opacity-100' : 'opacity-25' }}"
                                 viewBox="0 0 24 24" fill="currentColor">
                                <path d="m12 2 3 6.5 7 .9-5 4.8 1.2 7L12 17.8 5.8 21.2 7 14.2 2 9.4l7-.9L12 2z"/>
                            </svg>
                        @endfor
                    </div>

                    <blockquote class="mt-3 text-sm leading-relaxed">{{ $item['text'] }}</blockquote>

                    <figcaption class="mt-4 flex items-center gap-2.5 border-t border-border pt-3">
                        @if ($item['image'])
                            <img src="{{ \App\Support\Media::url($item['image']) }}" alt="" loading="lazy"
                                 class="size-8 shrink-0 rounded-full object-cover">
                        @else
                            {{-- Initial rather than a stock avatar: a generic face
                                 next to a named review reads as fabricated. --}}
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                {{ mb_substr($item['name'] ?: '؟', 0, 1) }}
                            </span>
                        @endif
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ $item['name'] }}</p>
                            @if ($item['city'])
                                <p class="truncate text-xs text-muted-foreground">{{ $item['city'] }}</p>
                            @endif
                        </div>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- Questions and answers, collapsed.

     Native <details>, not a JS accordion: it opens before any script has run,
     it is searchable in-page by the browser, and it costs nothing on a phone
     over a slow connection. --}}
@php($items = collect($settings['items'])->filter(fn ($i) => filled($i['question'])))

@if ($items->isNotEmpty())
<section class="px-4 py-8 md:px-5 md:py-10">
    <div class="mx-auto max-w-2xl">
        <h2 class="mb-4 text-[20px] font-bold leading-snug md:text-[22px]">{{ $settings['title'] }}</h2>

        <div class="ios-group">
            @foreach ($items as $item)
                <details class="group [&+&]:hairline-t">
                    <summary class="flex min-h-[52px] cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-[16px] font-medium">
                        {{ $item['question'] }}
                        <span class="shrink-0 text-muted-foreground transition-transform group-open:rotate-45">+</span>
                    </summary>
                    <p class="whitespace-pre-line px-4 pb-4 text-[15px] leading-relaxed text-muted-foreground">
                        {{ $item['answer'] }}
                    </p>
                </details>
            @endforeach
        </div>
    </div>
</section>
@endif

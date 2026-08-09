{{-- Questions and answers, collapsed.

     Native <details>, not a JS accordion: it opens before any script has run,
     it is searchable in-page by the browser, and it costs nothing on a phone
     over a slow connection. --}}
@php($items = collect($settings['items'])->filter(fn ($i) => filled($i['question'])))

@if ($items->isNotEmpty())
<section class="px-5 py-10">
    <div class="mx-auto max-w-2xl">
        <h2 class="mb-5 text-lg font-bold tracking-tight">{{ $settings['title'] }}</h2>

        <div class="divide-y divide-border overflow-hidden rounded-[--radius] border border-border bg-card">
            @foreach ($items as $item)
                <details class="group">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4 text-sm font-medium">
                        {{ $item['question'] }}
                        <span class="shrink-0 text-muted-foreground transition-transform group-open:rotate-45">+</span>
                    </summary>
                    <p class="whitespace-pre-line px-4 pb-4 text-sm leading-relaxed text-muted-foreground">
                        {{ $item['answer'] }}
                    </p>
                </details>
            @endforeach
        </div>
    </div>
</section>
@endif

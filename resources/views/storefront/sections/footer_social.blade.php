@php
    $items = collect($settings['items'])->filter(fn ($i) => filled($i['url']));

    $icons = [
        'facebook' => 'M14 9h3V6h-3a4 4 0 0 0-4 4v2H8v3h2v7h3v-7h2.5l.5-3h-3v-2a1 1 0 0 1 1-1z',
        'instagram' => 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zm5 5.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7zM17.5 6a.9.9 0 1 0 0 1.8.9.9 0 0 0 0-1.8z',
        'tiktok' => 'M15 3v9.5a3.5 3.5 0 1 1-3-3.46V12a1 1 0 1 0 1 1V3h2c.2 1.7 1.4 3 3 3v2c-1.2 0-2.2-.4-3-1z',
        'whatsapp' => 'M12 3a9 9 0 0 0-7.7 13.6L3 21l4.5-1.2A9 9 0 1 0 12 3zm4.3 12.2c-.2.5-1 1-1.5 1-.4 0-.9.2-3-.9s-3.4-3.6-3.6-3.8c-.2-.2-.9-1.2-.9-2.3s.6-1.6.8-1.8c.2-.2.4-.3.6-.3h.4c.2 0 .4 0 .5.4l.7 1.7c.1.2 0 .4-.1.5l-.3.4c-.1.1-.2.3 0 .6.2.3.7 1.2 1.5 1.9 1 .9 1.5 1 1.8 1.2.2.1.4 0 .5-.1l.6-.7c.2-.2.3-.2.5-.1l1.6.8c.2.1.4.2.4.3v.4z',
        'youtube' => 'M22 12s0-3-.4-4.4a2.5 2.5 0 0 0-1.8-1.8C18.4 5.4 12 5.4 12 5.4s-6.4 0-7.8.4a2.5 2.5 0 0 0-1.8 1.8C2 9 2 12 2 12s0 3 .4 4.4c.2.9.9 1.6 1.8 1.8 1.4.4 7.8.4 7.8.4s6.4 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8C22 15 22 12 22 12zM10 15V9l5 3-5 3z',
    ];
@endphp

@if ($items->isNotEmpty())
<div class="mx-auto max-w-5xl px-5 pt-8">
    <div class="flex justify-center gap-3">
        @foreach ($items as $item)
            {{-- `noopener` on every outbound link: without it the destination
                 page gets a handle on the storefront's window. --}}
            <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer"
               aria-label="{{ $item['platform'] }}"
               class="flex size-10 items-center justify-center rounded-full border border-border text-muted-foreground transition-colors hover:border-primary hover:text-primary">
                <svg class="size-5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="{{ $icons[$item['platform']] ?? $icons['facebook'] }}"/>
                </svg>
            </a>
        @endforeach
    </div>
</div>
@endif

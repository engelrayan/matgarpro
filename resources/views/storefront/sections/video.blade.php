{{-- A YouTube video that costs nothing until somebody wants it.

     The thumbnail is an image and a play button; the iframe is only inserted
     on click. An embedded player on load pulls ~700KB of YouTube's JavaScript
     into a page the merchant is paying per-visit to show. --}}
@php($id = \App\Support\Video::youtubeId($settings['url']))

@if ($id)
<section class="px-5 py-10">
    <div class="mx-auto max-w-3xl">
        @if ($settings['title'])
            <h2 class="mb-5 text-center text-lg font-bold tracking-tight">{{ $settings['title'] }}</h2>
        @endif

        <button type="button"
                class="group relative block w-full overflow-hidden rounded-[--radius] bg-black"
                data-video="{{ $id }}"
                aria-label="شغّل الفيديو">
            <img src="https://i.ytimg.com/vi/{{ $id }}/hqdefault.jpg" alt="" loading="lazy"
                 class="aspect-video w-full object-cover opacity-90 transition-opacity group-hover:opacity-100">
            <span class="absolute inset-0 flex items-center justify-center">
                <span class="flex size-16 items-center justify-center rounded-full bg-white/95 shadow-e3 transition-transform group-hover:scale-110">
                    <svg class="ms-1 size-7 text-black" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                </span>
            </span>
        </button>

        @if ($settings['caption'])
            <p class="mt-3 text-center text-sm text-muted-foreground">{{ $settings['caption'] }}</p>
        @endif
    </div>
</section>

@once
@push('scripts')
<script>
document.querySelectorAll('[data-video]').forEach((button) => {
    button.addEventListener('click', () => {
        const frame = document.createElement('iframe');
        // `autoplay=1` because the customer already pressed play — a second
        // click on YouTube's own button is a step that loses people.
        frame.src = `https://www.youtube-nocookie.com/embed/${button.dataset.video}?autoplay=1&rel=0`;
        frame.className = 'aspect-video w-full rounded-[--radius]';
        frame.allow = 'accelerometer; autoplay; encrypted-media; picture-in-picture';
        frame.allowFullscreen = true;
        frame.setAttribute('loading', 'lazy');
        button.replaceWith(frame);
    }, { once: true });
});
</script>
@endpush
@endonce
@endif

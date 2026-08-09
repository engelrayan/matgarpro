{{-- A YouTube video that costs nothing until somebody wants it.

     What renders on load is a thumbnail and a play button. The iframe is only
     inserted on click — an embedded player on load pulls ~700KB of YouTube's
     own JavaScript into a page the merchant pays for per visit, on behalf of a
     video most visitors never press.

     Shared by the product page and the builder's video block so the two can
     never drift into two different players. Expects `$videoId`. --}}
<button type="button"
        class="group relative block w-full overflow-hidden rounded-[--radius] bg-black"
        data-video="{{ $videoId }}"
        aria-label="شغّل الفيديو">
    <img src="https://i.ytimg.com/vi/{{ $videoId }}/hqdefault.jpg" alt="" loading="lazy"
         class="aspect-video w-full object-cover opacity-90 transition-opacity group-hover:opacity-100">
    <span class="absolute inset-0 flex items-center justify-center">
        <span class="flex size-16 items-center justify-center rounded-full bg-white/95 shadow-e3 transition-transform group-hover:scale-110">
            <svg class="ms-1 size-7 text-black" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
        </span>
    </span>
</button>

@once
@push('scripts')
<script>
document.querySelectorAll('[data-video]').forEach((button) => {
    button.addEventListener('click', () => {
        const frame = document.createElement('iframe');
        // `autoplay=1` because the customer already pressed play — making them
        // press YouTube's button too is a second step that loses people.
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

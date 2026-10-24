{{-- Designed images, one under the other, touching.

     How most Egyptian landing pages are actually made: a designer exports
     the ad creative as tall slices and they are stacked into the page. No gap
     between them, or the slices stop reading as one design. Lazy-loaded after
     the first, which is usually above the fold. --}}
@php($images = collect($settings['images'])->filter(fn ($item) => filled($item['image'])))

@if ($images->isNotEmpty())
<section @class(['mx-auto', 'max-w-[600px]' => $settings['width'] === 'narrow'])>
    @foreach ($images as $item)
        <img src="{{ \App\Support\Media::url($item['image']) }}" alt="{{ $item['alt'] }}"
             @if (! $loop->first) loading="lazy" @endif
             class="block h-auto w-full">
    @endforeach
</section>
@endif

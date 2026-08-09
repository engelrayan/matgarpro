@php($id = \App\Support\Video::youtubeId($settings['url']))

@if ($id)
<section class="px-5 py-10">
    <div class="mx-auto max-w-3xl">
        @if ($settings['title'])
            <h2 class="mb-5 text-center text-lg font-bold tracking-tight">{{ $settings['title'] }}</h2>
        @endif

        @include('storefront.partials.youtube', ['videoId' => $id])

        @if ($settings['caption'])
            <p class="mt-3 text-center text-sm text-muted-foreground">{{ $settings['caption'] }}</p>
        @endif
    </div>
</section>
@endif

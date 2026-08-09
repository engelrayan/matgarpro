{{-- Merchant-written prose: the store's story, a returns policy, whatever.

     `{!! !!}` is safe here and only here: the body went through HtmlSanitizer
     on save — the same allow-list the product description uses — so nothing
     outside a handful of formatting tags survived to reach this line. --}}
@if (filled($settings['heading']) || filled($settings['body']))
<section class="px-5 py-10">
    <div @class([
        'mx-auto',
        'max-w-2xl' => $settings['width'] === 'narrow',
        'max-w-5xl' => $settings['width'] === 'wide',
        'text-center' => $settings['align'] === 'center',
    ])>
        @if ($settings['heading'])
            <h2 class="text-xl font-bold tracking-tight md:text-2xl">{{ $settings['heading'] }}</h2>
        @endif

        @if ($settings['body'])
            <div class="prose-description mt-4 text-sm leading-relaxed text-muted-foreground">
                {!! $settings['body'] !!}
            </div>
        @endif
    </div>
</section>
@endif

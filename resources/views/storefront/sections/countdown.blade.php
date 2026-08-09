{{-- A deadline the merchant set.

     Rendered only while it is still in the future: the block disappears from
     the HTML the moment it passes, and the shared timer script removes it
     mid-view if it expires while somebody is on the page. A timer that has run
     out and is still sitting there is how a store teaches customers that its
     urgency is decoration. --}}
@php($ends = $settings['ends_at'] ? \Illuminate\Support\Carbon::parse($settings['ends_at']) : null)

@if ($ends && $ends->isFuture())
<section class="px-5 py-10">
    <div @class([
        'mx-auto max-w-4xl rounded-[--radius] p-8 text-center',
        'bg-primary text-primary-foreground' => $settings['style'] === 'solid',
        'border border-border bg-muted/50' => $settings['style'] === 'soft',
    ])>
        <h2 class="text-xl font-bold tracking-tight md:text-2xl">{{ $settings['title'] }}</h2>

        @if ($settings['subtitle'])
            <p class="mt-2 text-sm opacity-90">{{ $settings['subtitle'] }}</p>
        @endif

        <div class="mt-6 flex justify-center gap-3" data-countdown="{{ $ends->toIso8601String() }}">
            @foreach ([['d', 'يوم'], ['h', 'ساعة'], ['m', 'دقيقة'], ['s', 'ثانية']] as [$unit, $label])
                <div @class([
                    'min-w-16 rounded-[--radius] px-3 py-2.5 text-center',
                    'bg-white/15' => $settings['style'] === 'solid',
                    'bg-card shadow-e1' => $settings['style'] === 'soft',
                ])>
                    <span class="tabular block text-2xl font-bold leading-none" data-countdown-{{ $unit }}>--</span>
                    <span class="mt-1 block text-[10px] opacity-75">{{ $label }}</span>
                </div>
            @endforeach
        </div>

        @if ($settings['button_text'] && $settings['link'])
            <a href="{{ $settings['link'] }}"
               @class([
                   'btn mt-6 px-7 py-3',
                   'bg-white text-primary hover:brightness-95' => $settings['style'] === 'solid',
                   'btn-primary' => $settings['style'] === 'soft',
               ])>{{ $settings['button_text'] }}</a>
        @endif
    </div>
</section>

@include('storefront.partials.countdown-script')
@endif

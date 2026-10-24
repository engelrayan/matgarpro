{{-- The promise that takes the risk out of a first order.

     A seal, a title and the terms in plain words. Only rendered once the
     merchant has written a title — a guarantee is the one block that must
     never say something the shop does not actually do, so it has no default
     wording to fall back on. --}}
@if (filled($settings['title']))
<section class="px-4 py-10 md:px-5 md:py-14">
    <div class="mx-auto flex max-w-2xl flex-col items-center rounded-[28px] bg-gradient-to-b from-success/[0.09] to-success/[0.02] px-6 py-8 text-center ring-1 ring-success/15">
        <div class="relative flex size-24 items-center justify-center">
            <svg class="absolute inset-0 size-full text-success" viewBox="0 0 100 100" aria-hidden="true">
                <path fill="currentColor" d="M50 2l9 7 11-2 5 10 11 4-1 11 8 9-6 9 3 11-10 6-2 11-11 1-7 9-10-5-10 5-7-9-11-1-2-11-10-6 3-11-6-9 8-9-1-11 11-4 5-10 11 2z"/>
            </svg>
            <span class="relative px-2 text-[15px] font-extrabold leading-tight text-white">
                @if (filled($settings['badge']))
                    {{ $settings['badge'] }}
                @else
                    <svg class="mx-auto size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                @endif
            </span>
        </div>

        <h2 class="mt-4 text-balance text-[22px] font-extrabold leading-snug md:text-[26px]">{{ $settings['title'] }}</h2>

        @if (filled($settings['body']))
            <p class="mt-2 max-w-lg whitespace-pre-line text-pretty text-[15px] leading-relaxed text-muted-foreground">{{ $settings['body'] }}</p>
        @endif

        @if (filled($settings['button']) && isset($product))
            <a href="#orderForm" class="ios-btn ios-btn-lg ios-btn-filled mt-6 w-full md:w-auto md:px-10">{{ $settings['button'] }}</a>
        @endif
    </div>
</section>
@endif

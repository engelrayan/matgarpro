{{-- Proof that real people are buying — from the store's real orders.

     The competitor version of this block makes the names up. This one cannot:
     every line is an order in this store's database, shown as a governorate
     and a time, never a name or a number. With too little to show it shows
     nothing — "اتطلب مرتين الأسبوع ده" is worse than silence.

     The toasts are the same lines, one at a time in the corner, kept clear of
     the sticky buy bar and still under reduced motion. --}}
@php
    $week = $data['week'] ?? 0;
    $recent = collect($data['recent'] ?? [])->filter(fn ($order) => filled($order->governorate));

    $ago = function ($at): string {
        $minutes = max(1, (int) $at->diffInMinutes(now()));

        return match (true) {
            $minutes < 60 => "من {$minutes} دقيقة",
            $minutes < 120 => 'من ساعة',
            $minutes < 1440 => 'من ' . intdiv($minutes, 60) . ' ساعات',
            default => 'من ' . intdiv($minutes, 1440) . ' يوم',
        };
    };

    $showCount = $settings['show_count'] && $week >= 3;
    $showRecent = $settings['show_recent'] && $recent->isNotEmpty();
    $lines = $recent->map(fn ($order) => ['place' => $order->governorate, 'ago' => $ago($order->created_at)])->values();
@endphp

@if ($showCount || $showRecent)
<section class="px-4 py-6 md:px-5">
    <div class="mx-auto max-w-2xl">
        <div class="ios-group overflow-hidden">
            @if ($showCount)
                <div class="flex items-center gap-3 px-4 py-3.5">
                    <span class="relative flex size-2.5 shrink-0">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-60 motion-reduce:hidden"></span>
                        <span class="relative inline-flex size-2.5 rounded-full bg-success"></span>
                    </span>
                    <p class="text-[15px] font-semibold">
                        {{-- Arabic counts: ٣–١٠ take the plural, ١١ and up the singular. --}}
                        اتطلب <span class="tabular">{{ $week }}</span> {{ $week <= 10 ? 'مرات' : 'مرة' }} في آخر ٧ أيام
                    </p>
                </div>
            @endif

            @if ($showRecent)
                @foreach ($lines->take(3) as $line)
                    <div @class(['flex items-center justify-between gap-3 px-4 py-3 text-[14px]', 'hairline-t' => $showCount || ! $loop->first])>
                        <span class="flex items-center gap-2">
                            <svg class="size-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h2l2 10h11l2-7H6"/><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/></svg>
                            طلب جديد من {{ $line['place'] }}
                        </span>
                        <span class="shrink-0 text-[13px] text-muted-foreground">{{ $line['ago'] }}</span>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</section>

@if ($settings['toasts'] && $showRecent)
    <div id="liveToast" aria-live="polite"
         class="material pointer-events-none fixed bottom-24 end-3 z-30 flex max-w-[220px] translate-y-3 items-center gap-2 rounded-2xl px-3 py-2 opacity-0 shadow-lg transition duration-500 md:bottom-6 md:end-6">
        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-success/15 text-success">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
        </span>
        <span class="min-w-0 text-[12px] leading-snug">
            <span class="block font-semibold" data-place></span>
            <span class="block text-muted-foreground" data-ago></span>
        </span>
    </div>
    <script>
        (() => {
            const lines = @json($lines);
            const toast = document.getElementById('liveToast');
            if (!toast || !lines.length) return;
            const show = (i) => {
                const line = lines[i % lines.length];
                toast.querySelector('[data-place]').textContent = 'حد من ' + line.place + ' طلب المنتج ده';
                toast.querySelector('[data-ago]').textContent = line.ago;
                toast.classList.remove('opacity-0', 'translate-y-3');
                setTimeout(() => toast.classList.add('opacity-0', 'translate-y-3'), 4500);
            };
            let i = 0;
            setTimeout(function next() { show(i++); if (i < lines.length * 2) setTimeout(next, 14000); }, 6000);
        })();
    </script>
@endif
@endif

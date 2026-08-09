{{-- The hero's product shot — a looping demo of a real purchase.

     Four scenes: browsing, opening a product, filling the order, done. Built
     from the storefront's own tokens and the same generated artwork the
     showrooms use, so what a visitor watches here is literally what the product
     renders. A recorded video would go stale the first time we touch a theme.

     Every scene is in the DOM from the start and switched with CSS, so the
     whole sequence is there on first paint and nothing pops in late. --}}
@php
    $artwork = app(\App\Services\Demo\DemoArtwork::class);

    $shot = [
        ['name' => 'قميص قطن مصري', 'price' => '٣٩٩', 'was' => '٥٩٩', 'kind' => 'shirt', 'hue' => 210],
        ['name' => 'ساعة كلاسيك', 'price' => '١٬٢٥٠', 'was' => null, 'kind' => 'watch', 'hue' => 28],
        ['name' => 'حذاء رياضي', 'price' => '٨٩٠', 'was' => '١٬١٠٠', 'kind' => 'sneaker', 'hue' => 160],
    ];
@endphp

<div class="halo relative" data-demo>
    <div class="surface-lux overflow-hidden p-2 md:p-2.5">
        <div class="relative overflow-hidden rounded-xl border border-border bg-background">

            {{-- Browser chrome --}}
            <div class="flex items-center gap-2 border-b border-border bg-muted/40 px-4 py-2.5">
                <span class="size-2.5 rounded-full bg-destructive/40"></span>
                <span class="size-2.5 rounded-full bg-warning/40"></span>
                <span class="size-2.5 rounded-full bg-success/40"></span>
                <div class="mx-auto flex items-center gap-1.5 rounded-lg bg-card px-3 py-1 text-[11px] text-muted-foreground shadow-e1">
                    <svg class="size-3 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <span dir="ltr" data-demo-url>mahmoud.com</span>
                </div>
            </div>

            <div class="bg-primary py-1.5 text-center text-[10px] font-medium text-primary-foreground">
                الدفع عند الاستلام · شحن لكل المحافظات · استبدال ١٤ يوم
            </div>

            <div class="flex items-center justify-between border-b border-border px-5 py-3">
                <div class="flex items-center gap-2">
                    <span class="flex size-7 items-center justify-center rounded-lg bg-primary text-xs font-bold text-primary-foreground">م</span>
                    <span class="text-sm font-bold">متجر الأناقة</span>
                </div>
                <div class="hidden gap-4 text-[11px] text-muted-foreground sm:flex">
                    <span>رجالي</span><span>حريمي</span><span>أحذية</span>
                </div>
                <div class="h-6 w-28 rounded-lg border border-border"></div>
            </div>

            {{-- Fixed height so switching scenes never resizes the page --}}
            <div class="relative h-[19rem] overflow-hidden md:h-[21rem]">

                {{-- Scene 1 — browsing --}}
                <div class="demo-scene absolute inset-0 p-4 md:p-5" data-scene="0">
                    <div class="grid grid-cols-3 gap-3 md:gap-4">
                        @foreach ($shot as $i => $item)
                            <div class="overflow-hidden rounded-xl border border-border bg-card transition-transform duration-500"
                                 @if ($i === 0) data-demo-target @endif>
                                <div class="relative">
                                    {!! $artwork->render($item['kind'], $item['hue'], $item['name'], 'block aspect-square w-full') !!}
                                    @if ($item['was'])
                                        <span class="absolute right-2 top-2 rounded-full bg-destructive px-2 py-0.5 text-[9px] font-bold text-destructive-foreground">خصم</span>
                                    @endif
                                </div>
                                <div class="p-2.5">
                                    <p class="truncate text-[11px] font-medium">{{ $item['name'] }}</p>
                                    <p class="mt-1 flex items-baseline gap-1.5">
                                        <span class="tabular text-sm font-bold text-primary">{{ $item['price'] }}</span>
                                        @if ($item['was'])
                                            <span class="tabular text-[10px] text-muted-foreground line-through">{{ $item['was'] }}</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Scene 2 — the product page --}}
                <div class="demo-scene absolute inset-0 grid gap-5 p-4 md:grid-cols-2 md:p-5" data-scene="1">
                    <div class="overflow-hidden rounded-xl border border-border">
                        {!! $artwork->render('shirt', 210, 'قميص قطن مصري', 'block aspect-square w-full') !!}
                    </div>

                    <div class="flex flex-col justify-center">
                        <p class="text-base font-bold md:text-lg">قميص قطن مصري</p>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="tabular text-xl font-bold text-primary md:text-2xl">٣٩٩</span>
                            <span class="text-[10px] text-muted-foreground">ج.م</span>
                            <span class="tabular text-xs text-muted-foreground line-through">٥٩٩</span>
                            <span class="badge-danger !text-[9px]">−٣٣٪</span>
                        </div>

                        <p class="mt-3 text-[10px] text-muted-foreground">المقاس</p>
                        <div class="mt-1.5 flex gap-1.5">
                            @foreach (['S', 'M', 'L', 'XL'] as $size)
                                <span @class([
                                    'rounded-lg border px-2.5 py-1 text-[11px] transition-colors',
                                    'border-primary bg-primary/5 font-medium' => $size === 'M',
                                    'border-border text-muted-foreground' => $size !== 'M',
                                ])>{{ $size }}</span>
                            @endforeach
                        </div>

                        <div class="mt-4 flex h-9 items-center justify-center rounded-xl bg-primary text-xs font-medium text-primary-foreground">
                            اطلب دلوقتي
                        </div>
                    </div>
                </div>

                {{-- Scene 3 — the order form filling itself --}}
                <div class="demo-scene absolute inset-0 p-4 md:p-5" data-scene="2">
                    <p class="text-sm font-bold">بيانات التوصيل</p>
                    <p class="mt-0.5 text-[10px] text-muted-foreground">الدفع عند الاستلام — تدفع لما يوصلك</p>

                    <div class="mt-4 space-y-2.5">
                        @foreach ([
                            ['اسمك', 'سارة عبد الله', 'rtl'],
                            ['رقم الموبايل', '01223344556', 'ltr'],
                            ['المحافظة', 'الإسكندرية', 'rtl'],
                            ['العنوان بالتفصيل', 'سموحة، شارع فوزي معاذ، عمارة ١٢', 'rtl'],
                        ] as $i => [$label, $value, $dir])
                            <div>
                                <p class="mb-1 text-[10px] text-muted-foreground">{{ $label }}</p>
                                <div class="flex h-8 items-center rounded-lg border border-border bg-card px-3">
                                    <span class="text-[11px]" dir="{{ $dir }}" data-typed="{{ $value }}" data-typed-delay="{{ $i * 500 }}"></span>
                                    <span class="ms-px inline-block h-3 w-px animate-pulse bg-primary" data-caret></span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex h-9 items-center justify-center rounded-xl bg-primary text-xs font-medium text-primary-foreground">
                        تأكيد الطلب — ٧٩٨ ج.م
                    </div>
                </div>

                {{-- Scene 4 — done --}}
                <div class="demo-scene absolute inset-0 flex flex-col items-center justify-center p-6 text-center" data-scene="3">
                    <span class="flex size-14 items-center justify-center rounded-full bg-success/10 text-success">
                        <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    <p class="mt-4 text-lg font-bold">وصلنا طلبك</p>
                    <p class="mt-1.5 text-[11px] text-muted-foreground">
                        هنكلمك على <span dir="ltr" class="font-medium text-foreground">01223344556</span> نأكّد قبل الشحن
                    </p>

                    <div class="mt-5 w-full max-w-xs rounded-xl border border-border bg-muted/40 p-3 text-right">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-muted-foreground">رقم الطلب</span>
                            <span class="tabular font-bold">#١٢</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between border-t border-border pt-2 text-[11px]">
                            <span class="text-muted-foreground">الإجمالي</span>
                            <span class="tabular font-bold">٧٩٨٫٠٠ ج.م</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Cursor, moved by the script. Pointer-events off so it can never
                 sit between a visitor and the real page. --}}
            <span class="pointer-events-none absolute z-20 opacity-0 transition-all duration-700 ease-out" data-demo-cursor>
                <svg class="size-5 drop-shadow-md" viewBox="0 0 24 24" fill="white" stroke="black" stroke-width="1.2" stroke-linejoin="round">
                    <path d="m4 2 7 18 2.5-7.5L21 10z"/>
                </svg>
            </span>
        </div>
    </div>

    {{-- Two floating cards: the order landing, and the money arriving. --}}
    <div class="floaty absolute -bottom-6 -right-4 hidden w-52 rounded-2xl border border-border bg-card p-4 shadow-e3 transition-all duration-500 md:block"
         data-demo-toast>
        <div class="flex items-center gap-2">
            <span class="flex size-7 items-center justify-center rounded-full bg-success/10 text-success">
                <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>
            </span>
            <span class="text-xs font-semibold">طلب جديد</span>
        </div>
        <p class="mt-2 text-[11px] text-muted-foreground">سارة عبد الله · الإسكندرية</p>
        <p class="tabular mt-1 text-sm font-bold">٧٩٨٫٠٠ ج.م</p>
    </div>

    <div class="floaty absolute -left-4 -top-6 hidden w-48 rounded-2xl border border-gold-200/70 bg-card p-4 shadow-e3 md:block" style="animation-delay:-3s">
        <p class="text-[11px] text-muted-foreground">تحويل النهارده</p>
        <p class="text-foil tabular mt-1 text-xl font-bold">١٢٬٤٥٠ <span class="text-xs">ج.م</span></p>
        <p class="mt-1 text-[10px] text-muted-foreground">٣٨ طلب اتسلّم</p>
    </div>
</div>

@push('scripts')
<script>
/*
 * Drives the hero demo.
 *
 * Scene 1 is authored visible and the rest are hidden, so a visitor with no JS
 * — or with reduced motion — sees a normal, complete storefront shot rather
 * than an empty frame. Everything below only ever adds motion on top of that.
 */
(function () {
    const demo = document.querySelector('[data-demo]');
    if (!demo) return;

    const scenes = [...demo.querySelectorAll('[data-scene]')];
    const cursor = demo.querySelector('[data-demo-cursor]');
    const toast = demo.querySelector('[data-demo-toast]');
    const url = demo.querySelector('[data-demo-url]');
    const typed = [...demo.querySelectorAll('[data-typed]')];

    if (scenes.length < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    // From here the script owns which scene is visible; the CSS fallback that
    // pins scene 1 open steps aside.
    demo.classList.add('demo-ready');

    let timers = [];
    const at = (ms, fn) => timers.push(setTimeout(fn, ms));
    const clear = () => { timers.forEach(clearTimeout); timers = []; };

    function show(index) {
        scenes.forEach((s, i) => s.classList.toggle('is-active', i === index));
    }

    function moveCursor(target, offsetX = 0, offsetY = 0) {
        if (!target || !cursor) return;
        const frame = demo.getBoundingClientRect();
        const box = target.getBoundingClientRect();
        cursor.style.opacity = '1';
        cursor.style.left = `${box.left - frame.left + box.width / 2 + offsetX}px`;
        cursor.style.top = `${box.top - frame.top + box.height / 2 + offsetY}px`;
    }

    function type(el, text, done) {
        let i = 0;
        const step = () => {
            el.textContent = text.slice(0, ++i);
            if (i < text.length) at(28, step);
            else if (done) done();
        };
        step();
    }

    function run() {
        clear();
        typed.forEach((el) => (el.textContent = ''));
        if (toast) toast.style.opacity = '0';
        if (cursor) cursor.style.opacity = '0';
        if (url) url.textContent = 'mahmoud.com';

        // 1 — browsing, cursor drifts to the first product
        show(0);
        at(700, () => moveCursor(demo.querySelector('[data-demo-target]')));

        // 2 — product page
        at(1900, () => {
            show(1);
            if (url) url.textContent = 'mahmoud.com/p/cotton-shirt';
        });
        at(2600, () => moveCursor(scenes[1].querySelector('.bg-primary')));

        // 3 — the form fills itself
        at(3800, () => {
            show(2);
            if (cursor) cursor.style.opacity = '0';
            typed.forEach((el) =>
                at(Number(el.dataset.typedDelay || 0), () => type(el, el.dataset.typed)),
            );
        });

        // 4 — confirmed, and the merchant's notification lands
        at(8200, () => {
            show(3);
            if (url) url.textContent = 'mahmoud.com/thanks/12';
        });
        at(8800, () => { if (toast) toast.style.opacity = '1'; });

        at(13000, run);
    }

    // Only while it is on screen: an animation looping in a tab nobody is
    // looking at is battery someone else is paying for.
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => (entry.isIntersecting ? run() : clear()));
    }, { threshold: 0.25 });

    observer.observe(demo);
    document.addEventListener('visibilitychange', () => (document.hidden ? clear() : null));
})();
</script>
@endpush

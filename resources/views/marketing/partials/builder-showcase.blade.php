{{-- The store builder, played with rather than described.

     Self-contained on purpose: markup, styles and the small script all live in
     this one file, so the section can be moved, reordered or dropped from the
     landing page by touching a single `@include` line.

     It runs a short tour on its own, and **hands control to the visitor the
     moment they touch anything** — the point being made is "you can move this",
     and the fastest way to make it is to let them move it. No framework: this
     is the page ad traffic lands on, and it ships no application JS. --}}

@php
    /*
     | The parts of the fake home page, in their starting order.
     |
     | `settings` is what the left panel shows when each is selected — the same
     | plain-language labels the real builder uses, so nothing a visitor reads
     | here is a word they will not see again after signing up.
     */
    $parts = [
        ['key' => 'hero',   'name' => 'الصورة الكبيرة فوق', 'settings' => ['الصور تيجي منين؟' => 'من منتجاتك تلقائي', 'الصور تتبدّل لوحدها' => 'أيوه', 'تتبدّل كل كام ثانية؟' => '٦']],
        ['key' => 'trust',  'name' => 'مميزات متجرك',       'settings' => ['أول ميزة' => 'الدفع عند الاستلام', 'تاني ميزة' => 'شحن لكل المحافظات', 'تالت ميزة' => 'استبدال ١٤ يوم']],
        ['key' => 'deals',  'name' => 'العروض والخصومات',   'settings' => ['الكلام اللي فوق' => 'عروض النهارده', 'كام منتج يتعرض؟' => '٤', 'كام واحد في الصف؟' => '٤ في الصف']],
        ['key' => 'cats',   'name' => 'أقسام المتجر',        'settings' => ['الكلام اللي فوق' => 'تسوّق حسب القسم', 'كام واحد في الصف؟' => '٣ في الصف']],
        ['key' => 'grid',   'name' => 'كل المنتجات',         'settings' => ['يعرض إيه؟' => 'كل منتجاتي', 'كام منتج في الصفحة؟' => '٢٤']],
    ];

    /*
     | Product shots. The drawing itself lives in `builder-shot`, which is the
     | one place to change the day real photography exists — swap its <svg> for
     | an <img> and nothing on this page has to move.
     */
    $shots = [
        ['name' => 'قميص قطن مصري', 'price' => '٣٩٩', 'was' => '٥٩٩', 'hue' => 212, 'kind' => 'shirt'],
        ['name' => 'ساعة كلاسيك',   'price' => '١٬٢٥٠', 'was' => null,  'hue' => 28,  'kind' => 'watch'],
        ['name' => 'حذاء رياضي',    'price' => '٨٩٠',  'was' => '١٬١٠٠', 'hue' => 158, 'kind' => 'shoe'],
        ['name' => 'شنطة جلد',      'price' => '٦٥٠',  'was' => null,   'hue' => 22,  'kind' => 'bag'],
    ];

@endphp

<section class="relative overflow-hidden px-5 py-20 md:py-28" id="builder">
    <div class="mx-auto max-w-6xl">

        {{-- ── Heading ──────────────────────────────────────────────── --}}
        <div class="reveal mx-auto max-w-2xl text-center">
            <span class="badge-gold">جديد</span>

            <h2 class="mt-4 text-balance text-3xl font-bold leading-tight tracking-tight md:text-5xl">
                أنت اللي بتبني <span class="text-jade-gradient">شكل متجرك</span>
            </h2>

            <p class="mt-4 text-pretty leading-relaxed text-muted-foreground md:text-lg">
                اسحب أجزاء الصفحة، رتّبها، غيّر صورها وعناوينها — وشوف متجرك الحقيقي بيتغيّر قدامك
                لحظة بلحظة. من غير أكواد، ومن غير ما حد من زباينك يشوف حاجة قبل ما تكون جاهزة.
            </p>

            <div class="mt-7 flex flex-wrap items-center justify-center gap-2.5">
                @foreach (['اسحب ورتّب', 'شوف النتيجة فوراً', 'انشر لما تكون جاهز', 'من غير كود'] as $pill)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3.5 py-1.5 text-sm shadow-e1">
                        <svg class="size-4 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5"/>
                        </svg>
                        {{ $pill }}
                    </span>
                @endforeach
            </div>
        </div>

        {{-- ── The playable builder ─────────────────────────────────── --}}
        <div class="bx reveal mt-12" data-bx>

            <div class="surface-lux overflow-hidden p-2 md:p-2.5">
                <div class="overflow-hidden rounded-xl border border-border bg-background">

                    {{-- Chrome --}}
                    <div class="flex items-center gap-2 border-b border-border bg-muted/40 px-3 py-2.5 md:px-4">
                        <span class="size-2.5 rounded-full bg-destructive/40"></span>
                        <span class="size-2.5 rounded-full bg-warning/40"></span>
                        <span class="size-2.5 rounded-full bg-success/40"></span>

                        <span class="mx-auto hidden rounded-lg bg-card px-3 py-1 text-[11px] text-muted-foreground shadow-e1 sm:block">
                            تصميم الصفحة الرئيسية
                        </span>

                        <div class="mr-auto flex items-center gap-1 rounded-lg bg-card p-0.5 shadow-e1 sm:mr-0">
                            @foreach ([['desktop', 'M3 5h18v11H3zM9 20h6'], ['mobile', 'M8 3h8v18H8z']] as [$mode, $path])
                                <button type="button" class="bx-device rounded-md p-1" data-device="{{ $mode }}"
                                        aria-label="{{ $mode === 'desktop' ? 'شكل الكمبيوتر' : 'شكل الموبايل' }}">
                                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $path }}"/></svg>
                                </button>
                            @endforeach
                        </div>

                        <button type="button" class="bx-publish rounded-lg bg-primary px-3 py-1 text-[11px] font-medium text-primary-foreground">
                            نشر
                        </button>
                    </div>

                    <div class="grid md:grid-cols-[10rem_1fr_10rem]">

                        {{-- Parts rail --}}
                        <div class="order-2 border-t border-border p-2 md:order-none md:border-l md:border-t-0">
                            <p class="px-1.5 pb-2 text-[10px] font-medium text-muted-foreground">أجزاء الصفحة</p>

                            <div class="bx-list flex gap-1.5 overflow-x-auto md:block md:overflow-visible">
                                @foreach ($parts as $part)
                                    <button type="button"
                                            class="bx-row"
                                            draggable="true"
                                            data-part="{{ $part['key'] }}"
                                            data-settings='@json($part['settings'])'>
                                        <span class="bx-grip" aria-hidden="true"></span>
                                        <span class="truncate">{{ $part['name'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Preview --}}
                        <div class="bx-stage relative min-h-[22rem] bg-muted/30 p-3">
                            <div class="bx-frame">

                                <div class="bx-block" data-block="hero">
                                    <span class="bx-tag">الصورة الكبيرة</span>
                                    <div class="bx-hero">
                                        <div class="bx-hero__text">
                                            <span class="bx-badge">وفّر ٣٣٪</span>
                                            <b>قميص قطن مصري</b>
                                            <span class="bx-price">٣٩٩ <s>٥٩٩</s></span>
                                            <span class="bx-cta">اطلب دلوقتي</span>
                                        </div>
                                        <div class="bx-shot bx-shot--lg">
                                            @include('marketing.partials.builder-shot', ['kind' => 'shirt', 'hue' => 212])
                                        </div>
                                    </div>
                                </div>

                                <div class="bx-block" data-block="trust">
                                    <span class="bx-tag">المميزات</span>
                                    <div class="bx-trust">
                                        @foreach (['الدفع عند الاستلام', 'شحن لكل المحافظات', 'استبدال ١٤ يوم'] as $feature)
                                            <span><i></i>{{ $feature }}</span>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="bx-block" data-block="deals">
                                    <span class="bx-tag">العروض</span>
                                    <div class="bx-cards">
                                        @foreach ($shots as $shot)
                                            <figure class="bx-card">
                                                <div class="bx-shot">
                                                    @include('marketing.partials.builder-shot', ['kind' => $shot['kind'], 'hue' => $shot['hue']])
                                                    @if ($shot['was'])
                                                        <span class="bx-off">خصم</span>
                                                    @endif
                                                </div>
                                                <figcaption>
                                                    <b>{{ $shot['name'] }}</b>
                                                    <span class="bx-price">
                                                        {{ $shot['price'] }}
                                                        @if ($shot['was'])<s>{{ $shot['was'] }}</s>@endif
                                                    </span>
                                                </figcaption>
                                            </figure>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="bx-block" data-block="cats">
                                    <span class="bx-tag">الأقسام</span>
                                    <div class="bx-cats">
                                        @foreach ([['رجالي', 212], ['حريمي', 336], ['أحذية', 158]] as [$label, $hue])
                                            <span class="bx-cat">
                                                <span class="bx-cat__art" style="--h: {{ $hue }}"></span>
                                                {{ $label }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="bx-block" data-block="grid">
                                    <span class="bx-tag">كل المنتجات</span>
                                    <div class="bx-cards bx-cards--tight">
                                        @foreach ($shots as $shot)
                                            <figure class="bx-card">
                                                <div class="bx-shot">
                                                    @include('marketing.partials.builder-shot', ['kind' => $shot['kind'], 'hue' => $shot['hue']])
                                                </div>
                                            </figure>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <span class="bx-cursor" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="currentColor" class="size-5 drop-shadow"><path d="M5 3l14 8-6 1.5L10 19z"/></svg>
                            </span>

                            <span class="bx-toast" role="status">تم النشر ✓ متجرك اتحدّث</span>
                        </div>

                        {{-- Settings --}}
                        <div class="order-3 hidden border-r border-border p-2 md:order-none md:block">
                            <p class="px-1.5 pb-2 text-[10px] font-medium text-muted-foreground">إعدادات الجزء</p>
                            <div class="bx-settings"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Caption + hint --}}
            <div class="mt-5 flex flex-col items-center gap-2 text-center">
                <p class="bx-caption flex items-center gap-2 text-sm md:text-base">
                    <span class="bx-step">1</span>
                    <span class="bx-caption__text"></span>
                </p>
                <p class="bx-hint text-xs text-muted-foreground">
                    ↑ دي مش صورة — جرّب اسحب أي جزء أو دوس عليه بنفسك.
                </p>
            </div>
        </div>

        <div class="mt-10 text-center">
            <a href="{{ route('register') }}" class="btn-primary sheen px-8 py-3.5 text-base">جرّبه على متجرك</a>
            <p class="mt-3 text-sm text-muted-foreground">مجاني بالكامل — من غير كارت ائتمان.</p>
        </div>
    </div>
</section>

<style>
/* ── Rail ─────────────────────────────────────────────────────────── */
.bx-row {
    display: flex; align-items: center; gap: .4rem; width: 100%;
    border: 1px solid transparent; border-radius: .5rem;
    padding: .4rem .5rem; margin-bottom: .25rem;
    font-size: 11px; line-height: 1.2; text-align: start;
    cursor: grab; transition: background-color .15s, border-color .15s, transform .2s;
    flex: none;
}
.bx-row:hover { background: hsl(var(--muted)); }
.bx-row.is-active { background: hsl(var(--primary) / .08); border-color: hsl(var(--primary) / .5); font-weight: 600; }
.bx-row.is-dragging { opacity: .4; cursor: grabbing; }
.bx-row.is-over { border-color: hsl(var(--primary)); border-style: dashed; }
.bx-grip {
    width: 3px; height: 12px; border-radius: 2px; flex: none;
    background: hsl(var(--muted-foreground) / .35);
}

/* ── Stage ────────────────────────────────────────────────────────── */
.bx-frame {
    display: flex; flex-direction: column; gap: .45rem;
    margin-inline: auto; width: 100%;
    transition: max-width .35s ease;
}
.bx[data-device="mobile"] .bx-frame { max-width: 15rem; }

.bx-block {
    position: relative; border-radius: .6rem;
    border: 1px solid hsl(var(--border)); background: hsl(var(--card));
    padding: .55rem; cursor: pointer;
    transition: box-shadow .2s, transform .25s;
}
.bx-block:hover { box-shadow: 0 0 0 2px hsl(var(--primary) / .35); }
.bx-block.is-active { box-shadow: 0 0 0 2px hsl(var(--primary)), 0 0 0 6px hsl(var(--primary) / .15); }
.bx-block.is-flash { animation: bx-flash .7s ease; }
@keyframes bx-flash {
    0%   { box-shadow: 0 0 0 2px hsl(var(--primary)), 0 0 0 10px hsl(var(--primary) / .25); }
    100% { box-shadow: 0 0 0 2px hsl(var(--primary)), 0 0 0 6px hsl(var(--primary) / 0); }
}

.bx-tag {
    position: absolute; inset-inline-end: .4rem; top: .4rem; z-index: 2;
    border-radius: 999px; background: hsl(var(--muted));
    padding: .1rem .45rem; font-size: 9px; color: hsl(var(--muted-foreground));
}

/* ── Hero ─────────────────────────────────────────────────────────── */
.bx-hero {
    display: grid; grid-template-columns: 1fr auto; align-items: center; gap: .6rem;
    border-radius: .45rem; padding: .5rem .6rem;
    background: linear-gradient(100deg, hsl(var(--primary) / .10), hsl(var(--gold-300) / .16));
}
.bx-hero__text { display: flex; flex-direction: column; align-items: flex-start; gap: .25rem; font-size: 10px; }
.bx-hero__text b { font-size: 13px; }
.bx-badge {
    border-radius: 999px; background: hsl(var(--destructive)); color: #fff;
    padding: .05rem .35rem; font-size: 8px; font-weight: 700;
}
.bx-cta {
    margin-top: .15rem; border-radius: .35rem;
    background: hsl(var(--primary)); color: hsl(var(--primary-foreground));
    padding: .2rem .6rem; font-size: 9px; font-weight: 600;
}
.bx-price { font-weight: 700; color: hsl(var(--primary)); }
.bx-price s { color: hsl(var(--muted-foreground)); font-weight: 400; margin-inline-start: .25rem; }

/* ── Product shots ────────────────────────────────────────────────── */
.bx-shot {
    position: relative; aspect-ratio: 1; border-radius: .4rem; overflow: hidden;
    background: radial-gradient(120% 90% at 50% 12%, #fff 0%, hsl(40 14% 93%) 78%);
}
.bx-shot--lg { width: 4.6rem; }
.bx-shot svg { width: 100%; height: 100%; display: block; }
.bx-off {
    position: absolute; inset-inline-end: .2rem; top: .2rem;
    border-radius: 999px; background: hsl(var(--destructive)); color: #fff;
    padding: .05rem .3rem; font-size: 7px; font-weight: 700;
}

.bx-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: .35rem; margin-top: .9rem; }
.bx-cards--tight { gap: .25rem; }
.bx[data-device="mobile"] .bx-cards { grid-template-columns: repeat(2, 1fr); }

.bx-card { border-radius: .4rem; overflow: hidden; border: 1px solid hsl(var(--border)); background: hsl(var(--card)); }
.bx-card figcaption { display: flex; flex-direction: column; gap: .1rem; padding: .25rem .3rem; font-size: 8px; }
.bx-card b { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── Trust + categories ───────────────────────────────────────────── */
.bx-trust { display: grid; grid-template-columns: repeat(3, 1fr); gap: .3rem; margin-top: .9rem; font-size: 9px; }
.bx-trust span { display: flex; align-items: center; gap: .25rem; }
.bx-trust i {
    width: .8rem; height: .8rem; border-radius: 999px; flex: none;
    background: hsl(var(--primary) / .15);
}
.bx[data-device="mobile"] .bx-trust { grid-template-columns: 1fr; }

.bx-cats { display: grid; grid-template-columns: repeat(3, 1fr); gap: .3rem; margin-top: .9rem; font-size: 9px; }
.bx-cat { display: flex; flex-direction: column; gap: .2rem; }
.bx-cat__art {
    height: 1.9rem; border-radius: .3rem;
    background: linear-gradient(140deg, hsl(var(--h) 45% 78%), hsl(var(--h) 35% 58%));
}

/* ── Cursor, toast, settings ──────────────────────────────────────── */
.bx-cursor {
    position: absolute; inset-inline-start: 50%; top: 40%;
    color: hsl(var(--foreground)); opacity: 0; pointer-events: none;
    transition: inset-inline-start .7s ease, top .7s ease, opacity .3s;
    z-index: 5;
}
.bx.is-touring .bx-cursor { opacity: 1; }

.bx-toast {
    position: absolute; inset-inline-end: .6rem; bottom: .6rem; z-index: 5;
    border-radius: .5rem; background: hsl(var(--success)); color: #fff;
    padding: .3rem .6rem; font-size: 10px; font-weight: 600;
    opacity: 0; transform: translateY(.4rem); transition: opacity .25s, transform .25s;
}
.bx-toast.is-on { opacity: 1; transform: translateY(0); }

.bx-setting { padding: .25rem .4rem; }
.bx-setting span { display: block; font-size: 9px; color: hsl(var(--muted-foreground)); margin-bottom: .15rem; }
.bx-setting b {
    display: block; border-radius: .35rem; border: 1px solid hsl(var(--border));
    background: hsl(var(--card)); padding: .2rem .4rem; font-size: 10px; font-weight: 500;
}

.bx-device { color: hsl(var(--muted-foreground)); }
.bx-device.is-active { background: hsl(var(--muted)); color: hsl(var(--foreground)); }

.bx-step {
    flex: none; display: grid; place-items: center;
    width: 1.4rem; height: 1.4rem; border-radius: 999px;
    background: hsl(var(--primary)); color: hsl(var(--primary-foreground));
    font-size: .7rem; font-weight: 700;
}

@media (prefers-reduced-motion: reduce) {
    .bx-cursor { display: none; }
    .bx-frame, .bx-block, .bx-row { transition: none; }
}
</style>

<script>
/*
 * The demo drives itself until somebody touches it, then gets out of the way.
 *
 * An autoplaying loop proves nothing a video could not; letting a visitor drag
 * a block themselves is the entire argument for the product. So the tour runs
 * on a timer, and the first real interaction cancels it for good.
 */
(function () {
    const root = document.querySelector('[data-bx]');
    if (!root) return;

    const list = root.querySelector('.bx-list');
    const frame = root.querySelector('.bx-frame');
    const stage = root.querySelector('.bx-stage');
    const cursor = root.querySelector('.bx-cursor');
    const toast = root.querySelector('.bx-toast');
    const panel = root.querySelector('.bx-settings');
    const caption = root.querySelector('.bx-caption__text');
    const step = root.querySelector('.bx-step');
    const hint = root.querySelector('.bx-hint');

    const rows = () => [...list.querySelectorAll('.bx-row')];
    const blockFor = (key) => frame.querySelector(`[data-block="${key}"]`);

    let touring = true;
    let timers = [];

    const say = (n, text) => { step.textContent = n; caption.textContent = text; };

    function select(key, { flash = false } = {}) {
        rows().forEach((row) => row.classList.toggle('is-active', row.dataset.part === key));
        frame.querySelectorAll('.bx-block').forEach((b) => b.classList.toggle('is-active', b.dataset.block === key));

        const row = rows().find((r) => r.dataset.part === key);
        panel.innerHTML = '';

        Object.entries(JSON.parse(row?.dataset.settings ?? '{}')).forEach(([label, value]) => {
            const wrap = document.createElement('div');
            wrap.className = 'bx-setting';
            wrap.innerHTML = '<span></span><b></b>';
            wrap.querySelector('span').textContent = label;
            wrap.querySelector('b').textContent = value;
            panel.append(wrap);
        });

        const block = blockFor(key);

        if (flash && block) {
            block.classList.remove('is-flash');
            // Reading offsetWidth forces the class removal to take effect before
            // it is added again — otherwise re-selecting the same block does
            // nothing visible at all.
            void block.offsetWidth;
            block.classList.add('is-flash');
        }
    }

    /** Move a part (and its block) to a new position in the order. */
    function move(key, beforeKey) {
        const row = rows().find((r) => r.dataset.part === key);
        const target = beforeKey ? rows().find((r) => r.dataset.part === beforeKey) : null;
        const block = blockFor(key);
        const targetBlock = beforeKey ? blockFor(beforeKey) : null;

        if (!row || !block) return;

        target ? list.insertBefore(row, target) : list.append(row);
        targetBlock ? frame.insertBefore(block, targetBlock) : frame.append(block);
    }

    function stopTour() {
        if (!touring) return;
        touring = false;
        root.classList.remove('is-touring');
        timers.forEach(clearTimeout);
        timers = [];
        hint.textContent = '↑ اتفضل — رتّب الأجزاء زي ما تحب.';
    }

    // ── Real interaction ────────────────────────────────────────────
    rows().forEach((row) => {
        row.addEventListener('click', () => { stopTour(); select(row.dataset.part, { flash: true }); });

        row.addEventListener('dragstart', (e) => {
            stopTour();
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', row.dataset.part);
            row.classList.add('is-dragging');
        });

        row.addEventListener('dragend', () => {
            row.classList.remove('is-dragging');
            rows().forEach((r) => r.classList.remove('is-over'));
        });

        row.addEventListener('dragover', (e) => {
            e.preventDefault();
            rows().forEach((r) => r.classList.toggle('is-over', r === row));
        });

        row.addEventListener('drop', (e) => {
            e.preventDefault();
            rows().forEach((r) => r.classList.remove('is-over'));
            const key = e.dataTransfer.getData('text/plain');
            if (key && key !== row.dataset.part) {
                move(key, row.dataset.part);
                select(key, { flash: true });
            }
        });
    });

    frame.querySelectorAll('.bx-block').forEach((block) => {
        block.addEventListener('click', () => { stopTour(); select(block.dataset.block, { flash: true }); });
    });

    root.querySelectorAll('.bx-device').forEach((button) => {
        button.addEventListener('click', () => {
            stopTour();
            root.dataset.device = button.dataset.device;
            root.querySelectorAll('.bx-device').forEach((b) => b.classList.toggle('is-active', b === button));
        });
    });

    root.querySelector('.bx-publish').addEventListener('click', () => {
        stopTour();
        toast.classList.add('is-on');
        setTimeout(() => toast.classList.remove('is-on'), 2200);
    });

    // ── The tour ────────────────────────────────────────────────────
    function point(el) {
        if (!el || !touring) return;
        const box = el.getBoundingClientRect();
        const area = stage.getBoundingClientRect();
        cursor.style.insetInlineStart = `${((box.left + box.width / 2 - area.left) / area.width) * 100}%`;
        cursor.style.top = `${((box.top + box.height / 2 - area.top) / area.height) * 100}%`;
    }

    const script = [
        [0, () => { say(1, 'اختار أي جزء — كل حاجة في صفحتك بقت قطعة تقدر تمسكها.'); select('deals'); point(blockFor('deals')); }],
        [3200, () => { say(2, 'اسحبه مكانه — الترتيب بيتغيّر من غير كود ولا مطوّر.'); move('deals', 'trust'); point(blockFor('deals')); }],
        [6400, () => { say(3, 'ظبّط إعداداته — العنوان، الصور، وكام منتج في الصف.'); select('cats'); point(blockFor('cats')); }],
        [9600, () => { say(4, 'شوف النتيجة فوراً — دي مش صورة، ده متجرك وانت بتعدّل فيه.'); select('cats', { flash: true }); }],
        [12800, () => { say(5, 'دوس نشر — لحد ما تدوس، زباينك شايفين النسخة القديمة.'); point(root.querySelector('.bx-publish')); toast.classList.add('is-on'); }],
        [15600, () => { toast.classList.remove('is-on'); move('deals', 'cats'); select('hero'); }],
    ];

    function runTour() {
        if (!touring) return;
        timers = script.map(([at, fn]) => setTimeout(fn, at));
        timers.push(setTimeout(runTour, 18000));
    }

    // Nothing runs until the section is actually on screen — an animation
    // ticking away above the fold of a page nobody has scrolled to is pure
    // battery.
    select('hero');
    root.dataset.device = 'desktop';
    root.querySelector('.bx-device').classList.add('is-active');
    say(1, 'اختار أي جزء — كل حاجة في صفحتك بقت قطعة تقدر تمسكها.');

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        stopTour();
        return;
    }

    new IntersectionObserver((entries, observer) => {
        if (entries[0].isIntersecting && touring) {
            root.classList.add('is-touring');
            runTour();
            observer.disconnect();
        }
    }, { threshold: 0.35 }).observe(root);
})();
</script>

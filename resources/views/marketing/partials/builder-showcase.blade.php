{{-- The store builder, demonstrated rather than described.

     Self-contained on purpose: everything this section needs — markup, the
     keyframes, the caption timeline — lives in this one file, so it can be
     moved, reordered or dropped from the landing page by touching a single
     `@include` line.

     Pure CSS, no JavaScript. The landing page is where ad traffic arrives and
     ships no application JS at all; an animation that needs a script would be
     the first exception, and exceptions on a page bought with ad money get
     expensive. The whole timeline is one shared duration variable — change
     `--t` and every step re-times together. --}}

@php
    // Five steps, described once and rendered twice: as captions and as the
    // stage's own labels. Keeping them in one array is what stops the caption
    // and the thing it is pointing at from drifting apart.
    $steps = [
        ['اختار القسم', 'كل حاجة في صفحتك بقت قطعة تقدر تمسكها.'],
        ['اسحبه مكانه', 'الترتيب بيتغيّر بالسحب — من غير كود ولا مطوّر.'],
        ['ظبّط إعداداته', 'العنوان، الصور، عدد الأعمدة، المنتجات اللي تختارها بنفسك.'],
        ['شوف النتيجة فوراً', 'دي مش صورة — ده متجرك الحقيقي بيتفرّج عليه وانت بتعدّل.'],
        ['دوس نشر', 'لحد ما تدوس، زباينك شايفين النسخة القديمة. مفيش صفحة نص شغل.'],
    ];
@endphp

<section class="relative overflow-hidden px-5 py-20 md:py-28" id="builder">
    <div class="mx-auto max-w-6xl">

        {{-- ── Heading ──────────────────────────────────────────────── --}}
        {{-- `reveal` is the landing page's own scroll-in class, applied here so
             this section arrives the same way every other one does. --}}
        <div class="reveal mx-auto max-w-2xl text-center">
            <span class="badge-gold">جديد</span>

            <h2 class="mt-4 text-balance text-3xl font-bold leading-tight tracking-tight md:text-5xl">
                أنت اللي بتبني <span class="text-jade-gradient">شكل متجرك</span>
            </h2>

            <p class="mt-4 text-pretty leading-relaxed text-muted-foreground md:text-lg">
                اسحب الأقسام، رتّبها، غيّر صورها وعناوينها — وشوف متجرك الحقيقي بيتغيّر قدامك
                لحظة بلحظة. من غير أكواد، ومن غير ما حد من زباينك يشوف حاجة قبل ما تكون جاهزة.
            </p>

            <div class="mt-7 flex flex-wrap items-center justify-center gap-2.5">
                @foreach (['سحب وإفلات', 'معاينة حية', 'مسودة ونشر', 'من غير كود'] as $pill)
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3.5 py-1.5 text-sm shadow-e1">
                        <svg class="size-4 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 6 9 17l-5-5"/>
                        </svg>
                        {{ $pill }}
                    </span>
                @endforeach
            </div>
        </div>

        {{-- ── The stage ────────────────────────────────────────────── --}}
        <div class="bx reveal mt-12" style="--t: 20s">
            <div class="surface-lux overflow-hidden p-2 md:p-2.5">
                <div class="overflow-hidden rounded-xl border border-border bg-background">

                    {{-- Builder chrome --}}
                    <div class="flex items-center gap-2 border-b border-border bg-muted/40 px-4 py-2.5">
                        <span class="size-2.5 rounded-full bg-destructive/40"></span>
                        <span class="size-2.5 rounded-full bg-warning/40"></span>
                        <span class="size-2.5 rounded-full bg-success/40"></span>

                        <span class="mx-auto rounded-lg bg-card px-3 py-1 text-[11px] text-muted-foreground shadow-e1">
                            تصميم الصفحة الرئيسية
                        </span>

                        <span class="bx-publish rounded-lg bg-primary px-3 py-1 text-[11px] font-medium text-primary-foreground">
                            نشر
                        </span>
                    </div>

                    <div class="grid md:grid-cols-[9rem_1fr_9rem]">

                        {{-- Sections rail --}}
                        <div class="hidden border-l border-border p-2 md:block">
                            <p class="px-1.5 pb-2 text-[10px] font-medium text-muted-foreground">الأقسام</p>

                            <div class="bx-row bx-row--a">
                                <span class="bx-grip"></span> الهيرو
                            </div>
                            {{-- The one that moves. It starts third and ends
                                 second, which is the whole point of the demo. --}}
                            <div class="bx-row bx-row--moving">
                                <span class="bx-grip"></span> العروض
                            </div>
                            <div class="bx-row bx-row--b">
                                <span class="bx-grip"></span> الأقسام
                            </div>
                            <div class="bx-row">
                                <span class="bx-grip"></span> المنتجات
                            </div>
                            <div class="bx-row bx-row--muted">
                                <span class="bx-grip"></span> آراء العملاء
                            </div>
                        </div>

                        {{-- Preview --}}
                        <div class="bx-stage relative min-h-72 bg-muted/30 p-3 md:min-h-80">
                            <div class="bx-block bx-block--hero">
                                <span class="bx-tag">هيرو</span>
                            </div>

                            <div class="bx-block bx-block--deals">
                                <span class="bx-tag">عروض</span>
                                <div class="bx-cards">
                                    <i></i><i></i><i></i><i></i>
                                </div>
                            </div>

                            <div class="bx-block bx-block--cats">
                                <span class="bx-tag">أقسام</span>
                                <div class="bx-cards bx-cards--three">
                                    <i></i><i></i><i></i>
                                </div>
                            </div>

                            {{-- The cursor. Decorative, so it is hidden from
                                 assistive tech rather than announced as a shape
                                 nobody can act on. --}}
                            <span class="bx-cursor" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="currentColor" class="size-5 drop-shadow">
                                    <path d="M5 3l14 8-6 1.5L10 19z"/>
                                </svg>
                            </span>

                            <span class="bx-toast">تم النشر ✓</span>
                        </div>

                        {{-- Settings --}}
                        <div class="hidden border-r border-border p-2 md:block">
                            <p class="px-1.5 pb-2 text-[10px] font-medium text-muted-foreground">الإعدادات</p>

                            <div class="bx-field">
                                <span class="bx-label">العنوان</span>
                                <span class="bx-input bx-input--typing"></span>
                            </div>
                            <div class="bx-field">
                                <span class="bx-label">الأعمدة</span>
                                <span class="bx-input"></span>
                            </div>
                            <div class="bx-field">
                                <span class="bx-label">المنتجات</span>
                                <span class="bx-input"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Captions ─────────────────────────────────────────── --}}
            <div class="bx-captions mt-6">
                @foreach ($steps as $i => [$title, $body])
                    <p class="bx-caption" style="--i: {{ $i }}">
                        <span class="bx-step">{{ $i + 1 }}</span>
                        <span>
                            <span class="font-semibold">{{ $title }}</span>
                            <span class="text-muted-foreground"> — {{ $body }}</span>
                        </span>
                    </p>
                @endforeach
            </div>
        </div>

        <div class="mt-10 text-center">
            <a href="/register" class="btn-primary sheen px-8 py-3.5 text-base">جرّبه على متجرك</a>
            <p class="mt-3 text-sm text-muted-foreground">مجاني بالكامل — من غير كارت ائتمان.</p>
        </div>
    </div>
</section>

<style>
/*
 | One timeline, five equal steps.
 |
 | `--t` on the container is the whole cycle; every keyframe below is written
 | in percentages of it, so the animation stays in sync no matter what it is
 | changed to. Step N owns the window [N*20%, (N+1)*20%].
 */
.bx { --step: 20%; }

/* ── Section rows ─────────────────────────────────────────────────── */
.bx-row {
    display: flex; align-items: center; gap: .4rem;
    border-radius: .5rem; padding: .4rem .5rem;
    font-size: 11px; line-height: 1;
    border: 1px solid transparent;
}
.bx-row--muted { opacity: .45; }
.bx-grip {
    width: 3px; height: 12px; border-radius: 2px;
    background: hsl(var(--muted-foreground) / .35); flex: none;
}

/* The dragged row: picked up in step 1, dropped one slot up in step 2. */
.bx-row--moving {
    background: hsl(var(--primary) / .07);
    border-color: hsl(var(--primary) / .5);
    animation: bx-drag var(--t) infinite;
    position: relative; z-index: 2;
}
.bx-row--a { animation: bx-nudge-down var(--t) infinite; }

@keyframes bx-drag {
    0%, 8%    { transform: translateY(0); box-shadow: none; }
    14%       { transform: translateY(0) scale(1.03); box-shadow: 0 8px 20px hsl(var(--foreground) / .18); }
    24%       { transform: translateY(-1.6rem) scale(1.03); box-shadow: 0 8px 20px hsl(var(--foreground) / .18); }
    30%, 100% { transform: translateY(-1.6rem); box-shadow: none; }
}

/* The row it swaps with slides down to make room. */
@keyframes bx-nudge-down {
    0%, 16%   { transform: translateY(0); }
    26%, 100% { transform: translateY(1.6rem); }
}

/* ── Preview blocks ───────────────────────────────────────────────── */
.bx-stage { display: flex; flex-direction: column; gap: .5rem; }

.bx-block {
    position: relative;
    border-radius: .6rem;
    border: 1px solid hsl(var(--border));
    background: hsl(var(--card));
    padding: .6rem;
}
.bx-block--hero { height: 4.5rem; background: linear-gradient(100deg, hsl(var(--primary) / .12), hsl(var(--accent) / .12)); }

/* Order follows the rail: the deals block rises above the categories block at
   the same moment the row does, because that is the promise being made. */
.bx-block--deals { order: 2; animation: bx-highlight var(--t) infinite; }
.bx-block--cats  { order: 3; animation: bx-reorder var(--t) infinite; }

@keyframes bx-reorder {
    0%, 24%   { order: 2; transform: translateY(-3.6rem); opacity: 0; }
    26%, 100% { order: 3; transform: translateY(0); opacity: 1; }
}

/* Step 4: the preview flashes to say "this updated". */
@keyframes bx-highlight {
    0%, 58%   { box-shadow: none; }
    64%       { box-shadow: 0 0 0 2px hsl(var(--primary)), 0 0 0 6px hsl(var(--primary) / .2); }
    74%, 100% { box-shadow: none; }
}

.bx-tag {
    position: absolute; inset-inline-end: .4rem; top: .4rem;
    border-radius: 999px; background: hsl(var(--muted));
    padding: .1rem .45rem; font-size: 9px; color: hsl(var(--muted-foreground));
}
.bx-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: .35rem; margin-top: 1rem; }
.bx-cards--three { grid-template-columns: repeat(3, 1fr); }
.bx-cards i { display: block; height: 1.9rem; border-radius: .35rem; background: hsl(var(--muted)); }

/* ── Cursor ───────────────────────────────────────────────────────── */
.bx-cursor {
    position: absolute; inset-inline-start: 60%; top: 30%;
    color: hsl(var(--foreground));
    animation: bx-cursor var(--t) infinite;
}
@keyframes bx-cursor {
    0%       { inset-inline-start: 70%; top: 55%; opacity: 0; }
    6%       { opacity: 1; }
    14%      { inset-inline-start: 88%; top: 34%; }
    26%      { inset-inline-start: 88%; top: 18%; }
    44%      { inset-inline-start: 6%;  top: 30%; }
    62%      { inset-inline-start: 45%; top: 45%; }
    82%      { inset-inline-start: 30%; top: -6%; }
    92%, 100%{ inset-inline-start: 30%; top: -6%; opacity: 0; }
}

/* ── Settings panel ───────────────────────────────────────────────── */
.bx-field { padding: .3rem .4rem; }
.bx-label { display: block; font-size: 9px; color: hsl(var(--muted-foreground)); margin-bottom: .2rem; }
.bx-input {
    display: block; height: 1.1rem; border-radius: .35rem;
    border: 1px solid hsl(var(--border)); background: hsl(var(--card));
}
/* Step 3: a caret runs across the field, as if it were being typed into. */
.bx-input--typing { position: relative; overflow: hidden; }
.bx-input--typing::after {
    content: ''; position: absolute; inset-block: .2rem; inset-inline-start: .25rem;
    width: 2px; background: hsl(var(--primary));
    animation: bx-type var(--t) infinite;
}
@keyframes bx-type {
    0%, 40%   { transform: translateX(0); opacity: 0; }
    44%       { opacity: 1; }
    56%       { transform: translateX(-3.2rem); opacity: 1; }
    60%, 100% { opacity: 0; }
}

/* ── Publish + toast ──────────────────────────────────────────────── */
.bx-publish { animation: bx-publish var(--t) infinite; }
@keyframes bx-publish {
    0%, 78%   { box-shadow: none; }
    84%       { box-shadow: 0 0 0 4px hsl(var(--primary) / .3); }
    90%, 100% { box-shadow: none; }
}

.bx-toast {
    position: absolute; inset-inline-end: .6rem; bottom: .6rem;
    border-radius: .5rem; background: hsl(var(--success)); color: #fff;
    padding: .3rem .6rem; font-size: 10px; font-weight: 600;
    opacity: 0; transform: translateY(.4rem);
    animation: bx-toast var(--t) infinite;
}
@keyframes bx-toast {
    0%, 84%    { opacity: 0; transform: translateY(.4rem); }
    88%, 96%   { opacity: 1; transform: translateY(0); }
    100%       { opacity: 0; }
}

/* ── Captions ─────────────────────────────────────────────────────── */
.bx-captions { position: relative; min-height: 3.5rem; }
.bx-caption {
    position: absolute; inset-inline: 0; top: 0;
    display: flex; align-items: flex-start; gap: .6rem;
    justify-content: center; text-align: start;
    font-size: .95rem; opacity: 0;
    /* Each caption waits for its own fifth of the cycle. */
    animation: bx-caption var(--t) infinite;
    animation-delay: calc(var(--i) * var(--t) / 5);
}
@keyframes bx-caption {
    0%              { opacity: 0; transform: translateY(.4rem); }
    2%, 18%         { opacity: 1; transform: translateY(0); }
    20%, 100%       { opacity: 0; }
}
.bx-step {
    flex: none; display: grid; place-items: center;
    width: 1.4rem; height: 1.4rem; border-radius: 999px;
    background: hsl(var(--primary)); color: hsl(var(--primary-foreground));
    font-size: .7rem; font-weight: 700;
}

/*
 | Motion is the point of this section, but it is not worth making anybody ill.
 | With reduced motion the stage holds its final arrangement and every caption
 | is simply listed — the same information, standing still.
 */
@media (prefers-reduced-motion: reduce) {
    .bx * { animation: none !important; }
    .bx-row--moving { transform: translateY(-1.6rem); }
    .bx-row--a { transform: translateY(1.6rem); }
    .bx-captions { min-height: 0; }
    .bx-caption { position: static; opacity: 1; margin-bottom: .5rem; }
    .bx-cursor, .bx-toast { display: none; }
}
</style>

@extends('marketing.layout')

@section('title', 'متجر برو — احكي عن مشروعك، والذكاء الاصطناعي يجهّز متجرك')
@section('description', 'اكتب سطرين عن اللي بتبيعه، والذكاء الاصطناعي يقترح شكل متجرك ويعمل الأقسام ويكتب الصفحة الرئيسية ووصف منتجاتك — وإنت بتراجع وتنشر. أول ٣ شهور الطلب بنص جنيه، من غير اشتراك شهري. شحن مع أكتر من ٣٠٠ شركة عبر شبكة سندباد، وتحصيل يومي مع ضمان.')

@section('content')

{{-- ══ 1. Hero ══════════════════════════════════════════════════════════
     The product is "describe your business and the store is set up for you",
     so the hero shows exactly that happening: a merchant's two lines being
     typed, then a store assembling under them.

     It shows only what the AI actually does — picks the look, makes the
     categories, writes the home page — and nothing it does not: no invented
     products, no invented prices. A demo that over-promises is the first
     thing a merchant catches after signing up.

     Everything is visible without JavaScript; the script only replays it.
--}}
<section class="relative overflow-hidden">
    <div class="mesh pointer-events-none absolute inset-0 opacity-80"></div>

    <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 pb-16 pt-10 md:px-6 md:pb-24 md:pt-16 lg:grid-cols-[1.05fr_1fr] lg:gap-16">
        {{-- ── Copy ─────────────────────────────────────────────────── --}}
        <div class="text-center lg:text-start">
            <span class="reveal inline-flex items-center gap-1.5 rounded-full bg-card/80 px-3 py-1.5 text-[13px] font-semibold shadow-[0_0_0_0.5px_hsl(var(--foreground)/0.1)] backdrop-blur">
                <svg class="size-4 text-primary" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.9 5.6L19.5 9.5l-5.6 1.9L12 17l-1.9-5.6L4.5 9.5l5.6-1.9zM19 14l.9 2.6 2.6.9-2.6.9L19 21l-.9-2.6-2.6-.9 2.6-.9z"/></svg>
                <span class="ai-text">متجرك بالذكاء الاصطناعي</span>
            </span>

            <h1 class="reveal mt-5 text-balance text-[2.5rem] font-bold leading-[1.15] md:text-6xl md:leading-[1.12]">
                احكي عن مشروعك في سطرين.
                <span class="ai-text">والمتجر يتجهّز لوحده.</span>
            </h1>

            <p class="reveal mx-auto mt-5 max-w-xl text-balance text-[17px] leading-relaxed text-muted-foreground md:text-xl lg:mx-0">
                متجرك الإلكتروني جاهز في دقايق: الذكاء الاصطناعي بيقترح الشكل، ويعمل الأقسام،
                ويكتب الصفحة الرئيسية ووصف منتجاتك من صورها — وإنت بتراجع وتنشر.
            </p>

            {{-- The first question the AI builder asks, asked here. What they
                 type rides through signup (StartWithAiController) and is
                 waiting in the builder's form when they land there. --}}
            <div class="reveal mt-8">
                <form method="POST" action="{{ route('start') }}" class="ai-ring mx-auto max-w-xl rounded-[26px] text-start lg:mx-0">
                    @csrf
                    <div class="flex flex-col gap-2 rounded-[24.5px] bg-card p-2 sm:flex-row sm:items-center">
                        <label for="hero-business" class="sr-only">بتبيع إيه؟</label>
                        {{-- 16px or larger, or iOS zooms the page on focus. --}}
                        <input id="hero-business" name="business" type="text" maxlength="600" autocomplete="off"
                               placeholder="بتبيع إيه؟ مثلاً: هدوم أطفال قطن"
                               class="min-h-[52px] min-w-0 flex-1 rounded-[18px] bg-transparent px-4 text-[16px] outline-none placeholder:text-muted-foreground/80 focus-visible:bg-foreground/[0.03]">
                        <button type="submit" class="btn-primary !min-h-[52px] shrink-0 !rounded-[18px] !px-6 !text-[17px] shadow-[0_10px_30px_-10px_hsl(var(--primary)/0.7)]">
                            <svg class="size-[18px]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.9 5.6L19.5 9.5l-5.6 1.9L12 17l-1.9-5.6L4.5 9.5l5.6-1.9zM19 14l.9 2.6 2.6.9-2.6.9L19 21l-.9-2.6-2.6-.9 2.6-.9z"/></svg>
                            جهّزلي متجري
                        </button>
                    </div>
                </form>

                <a href="#ai" class="mt-4 inline-flex items-center gap-1 text-[15px] font-semibold text-primary hover:underline">
                    شوف الذكاء الاصطناعي بيعمل إيه
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                </a>
            </div>

            <p class="reveal mt-4 text-[13px] text-muted-foreground">
                أول ٣ شهور الطلب بنص جنيه · من غير اشتراك شهري · متجرك يفضل ليك
            </p>
        </div>

        {{-- ── The demo ─────────────────────────────────────────────── --}}
        <div class="reveal mx-auto w-full max-w-[26rem]" data-ai-demo>
            <div class="ai-ring rounded-[30px] shadow-e4">
                <div class="rounded-[28.5px] bg-card p-4 md:p-5">
                    {{-- What the merchant typed. --}}
                    <div class="rounded-[18px] bg-foreground/[0.04] p-3.5">
                        <p class="text-[12px] font-medium text-muted-foreground">بتبيع إيه؟</p>
                        <p class="mt-1 min-h-[3.25rem] text-[15px] leading-relaxed" data-ai-typed>عطور شرقية وبخور ومباخر. زبايني ستات ورجالة من ٢٥ لـ ٤٥، وعايز الشكل فخم وهادي.</p>
                    </div>

                    <div class="mt-3 flex items-center gap-2 px-1 text-[13px] font-medium" aria-live="polite">
                        <svg class="size-4 shrink-0 text-primary" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.9 5.6L19.5 9.5l-5.6 1.9L12 17l-1.9-5.6L4.5 9.5l5.6-1.9z"/></svg>
                        <span class="text-muted-foreground" data-ai-status>جاهز — مسودة مستنية مراجعتك</span>
                    </div>

                    {{-- What came out. Each block is one thing the generator
                         really writes, labelled with the builder's own name
                         for it. --}}
                    <div class="mt-3 overflow-hidden rounded-[18px] shadow-[0_0_0_0.5px_hsl(var(--foreground)/0.1)]">
                        <div data-ai-step class="flex items-center justify-between bg-[hsl(30_8%_12%)] px-3 py-1.5 text-[10.5px] text-[hsl(40_30%_92%)]">
                            <span>الدفع عند الاستلام · شحن لكل المحافظات</span>
                            <span class="opacity-60">الشريط اللي فوق</span>
                        </div>

                        <div data-ai-step class="flex items-center gap-2 border-b-[0.5px] border-foreground/10 bg-card px-3 py-2.5">
                            <span class="flex size-7 items-center justify-center rounded-md bg-[hsl(30_8%_12%)] text-[13px] font-bold text-[hsl(28_60%_62%)]">ع</span>
                            <span class="text-[14px] font-bold">بيت العود</span>
                            <span class="ms-auto rounded-full bg-[hsl(28_60%_52%/0.15)] px-2 py-0.5 text-[10.5px] font-semibold text-[hsl(28_55%_38%)]">الشكل: نوار</span>
                        </div>

                        <div data-ai-step class="relative bg-gradient-to-l from-[hsl(30_10%_16%)] to-[hsl(28_30%_26%)] px-4 py-5 text-[hsl(40_30%_96%)]">
                            <p class="text-[11px] opacity-60">عن المتجر</p>
                            <p class="mt-0.5 text-[17px] font-bold leading-snug">ريحة بتفضل معاك طول اليوم</p>
                            <p class="mt-1 text-[12px] leading-relaxed opacity-75">في بيت العود بنختار العطور الشرقية والبخور بإيدينا، علشان كل هدية تبان غالية.</p>
                        </div>

                        <div data-ai-step class="flex gap-1.5 overflow-hidden bg-card px-3 py-2.5">
                            @foreach (['عطور رجالي', 'عطور حريمي', 'بخور ومباخر', 'هدايا'] as $category)
                                <span class="shrink-0 rounded-full bg-foreground/[0.06] px-2.5 py-1 text-[11px] font-medium">{{ $category }}</span>
                            @endforeach
                        </div>

                        <div data-ai-step class="grid grid-cols-3 gap-px bg-foreground/[0.06]">
                            @foreach (['الدفع عند الاستلام', 'شحن ٢–٤ أيام', 'استبدال ١٤ يوم'] as $point)
                                <span class="bg-card px-1.5 py-2 text-center text-[10.5px] font-medium text-muted-foreground">{{ $point }}</span>
                            @endforeach
                        </div>

                        <div data-ai-step class="bg-card px-3 py-2.5">
                            <p class="text-[11px] font-semibold">أسئلة وأجوبة</p>
                            <p class="mt-1 text-[11px] text-muted-foreground">العطور أصلية؟ · الشحن بياخد قد إيه؟ · أقدر أبدّل؟</p>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-3 px-1">
                        <span class="text-[12px] text-muted-foreground">مش هيظهر لحد غير لما تنشر</span>
                        <button type="button" class="ios-btn-tinted rounded-full px-3 py-1.5 text-[12px] font-semibold" data-ai-replay hidden>
                            شغّلها تاني
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ══ 2. Proof strip ═══════════════════════════════════════════════════ --}}
<section class="hairline-t hairline-b bg-card">
    <div class="mx-auto grid max-w-6xl grid-cols-2 px-4 md:grid-cols-4 md:px-6">
        @foreach ([
            ['أقل من دقيقة', 'والذكاء الاصطناعي يجهّز متجرك'],
            ['+٣٠٠', 'شركة شحن'],
            ['كل يوم', 'تحصيل فلوسك'],
            ['٥٠ قرش', 'على الطلب — أول ٣ شهور'],
        ] as $stat)
            <div class="reveal px-3 py-6 text-center">
                <p class="text-foil text-[22px] font-bold md:text-3xl">{{ $stat[0] }}</p>
                <p class="mt-1 text-[13px] text-muted-foreground md:text-sm">{{ $stat[1] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- ══ 3. What the AI does ══════════════════════════════════════════════
     The two jobs it really does today, and the rule both follow. Worded in the
     same terms the dashboard uses ("جهّزلي المتجر", "اكتبلي البيانات"), so the
     first screen after sign-up is something the merchant already recognises.
--}}
<section id="ai" class="py-20 md:py-28">
    <div class="mx-auto max-w-6xl px-4 md:px-6">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="reveal text-balance text-3xl font-bold leading-tight md:text-5xl">
                الشغل التقيل <span class="ai-text">علينا</span>، والقرار ليك
            </h2>
            <p class="reveal mt-4 text-balance text-[17px] text-muted-foreground">
                الذكاء الاصطناعي بيعمل المسودة في ثواني. إنت بتبص، تعدّل اللي محتاجه، وتنشر.
            </p>
        </div>

        <div class="mt-12 grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-5">
            {{-- Store generator --}}
            <div class="reveal surface flex flex-col overflow-hidden p-6 md:p-8">
                <span class="ai-ring flex size-12 items-center justify-center rounded-[14px] !p-0">
                    <span class="flex size-[45px] items-center justify-center rounded-[12.5px] bg-card text-primary">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9 12 2l9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
                    </span>
                </span>
                <h3 class="mt-5 text-title-2">جهّزلي المتجر</h3>
                <p class="mt-2 text-callout text-muted-foreground">
                    قول بتبيع إيه، ومين زباينك، وعايز الشكل يبان إزاي. في أقل من دقيقة بيقترح
                    الشكل، ويعمل الأقسام، ويكتب الصفحة الرئيسية: الشريط اللي فوق، مميزات متجرك،
                    «عن المتجر»، الأسئلة والأجوبة، وآخر الصفحة.
                </p>

                <ul class="mt-6 space-y-2.5 text-[15px]">
                    @foreach ([
                        'كلام الشحن والاستبدال بياخده منك إنت — مش بيألّف مواعيد ولا وعود',
                        'الأقسام بتتعمل فاضية، ومش بتظهر للزبون غير لما تحط فيها منتجات',
                        'كله بيتحط مسودة في «تصميم المتجر» لحد ما تدوس نشر',
                    ] as $point)
                        <li class="flex items-start gap-2.5">
                            <svg class="mt-1 size-4 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Product writer --}}
            <div class="reveal surface flex flex-col overflow-hidden p-6 md:p-8">
                <span class="ai-ring flex size-12 items-center justify-center rounded-[14px] !p-0">
                    <span class="flex size-[45px] items-center justify-center rounded-[12.5px] bg-card text-primary">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.5-3.5L9 20"/></svg>
                    </span>
                </span>
                <h3 class="mt-5 text-title-2">اكتبلي المنتج</h3>
                <p class="mt-2 text-callout text-muted-foreground">
                    ارفع صور المنتج، وزوّد كلمتين لو حابب. في حوالي نص دقيقة بيكتب الاسم، ووصف
                    بيبيع، والمقاسات والألوان، ويختار القسم، ويكتب اللي هيظهر في جوجل.
                </p>

                {{-- A preview of the card it fills, not a screenshot. --}}
                <div class="mt-6 flex gap-3 rounded-[16px] bg-foreground/[0.035] p-3">
                    <img src="/images/landing/shirt.jpg" alt="" loading="lazy" decoding="async" width="400" height="400"
                         class="size-20 shrink-0 rounded-[12px] object-cover">

                    <div class="min-w-0 flex-1 space-y-1.5 pt-0.5">
                        <p class="text-[14px] font-semibold">قميص قطن مصري — قصّة مريحة</p>
                        <p class="text-[12px] leading-relaxed text-muted-foreground">قطن ١٠٠٪ بيتنفّس في الحر، وزراير متخيّطة كويس…</p>
                        <div class="flex flex-wrap gap-1">
                            @foreach (['المقاس: M، L، XL', 'اللون: رمادي، أبيض', 'القسم: رجالي'] as $chip)
                                <span class="rounded-full bg-card px-2 py-0.5 text-[10.5px] font-medium shadow-[0_0_0_0.5px_hsl(var(--foreground)/0.1)]">{{ $chip }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>

                <p class="mt-auto pt-6 text-[13px] text-muted-foreground">
                    الكلام بيتحط في خانات المنتج، ومفيش حاجة بتتحفظ غير لما تدوس «حفظ».
                </p>
            </div>
        </div>
    </div>
</section>

{{-- ══ 4. The store builder ═════════════════════════════════════════════ --}}
{{-- Straight after the AI section: "أنت اللي بتبني شكل متجرك" answers the
     objection that follows "it builds the store for you" — whether the merchant
     can still change what it made. Self-contained, so it can be moved or
     removed by touching this one line. --}}
@include('marketing.partials.builder-showcase')

{{-- ══ 5. Shipping & Daman — the reason to pick us ══════════════════════ --}}
<section id="shipping" class="mesh-ink relative overflow-hidden py-20 text-jade-50 md:py-28">
    <div class="relative mx-auto max-w-6xl px-4 md:px-6">
        <div class="mx-auto max-w-2xl text-center">
            <span class="reveal badge-gold mb-5">الشحن والتحصيل</span>
            <h2 class="reveal text-balance text-3xl font-bold leading-tight tracking-tight md:text-5xl">
                دي الحتة اللي
                <span class="text-foil">محدش بيعملها غيرنا</span>
            </h2>
            <p class="reveal mt-5 text-balance leading-relaxed text-jade-200/85">
                منصات المتاجر بتسيبك عند «الأوردر اتعمل». إحنا بنكمّل معاك لحد ما البضاعة
                توصل والفلوس تدخل حسابك.
            </p>
        </div>

        <div class="mt-14 grid gap-5 md:grid-cols-3">
            @foreach ([
                ['+٣٠٠', 'شركة شحن', 'عبر شبكة سندباد للشحن والنقل. مش شركة واحدة تفرض عليك سعرها — شبكة بتتنافس على شحنتك.'],
                ['أرخص', 'سعر متاح', 'الشبكة بتقارن الأسعار على محافظة العميل وتديك أحسن سعر، مش تعريفة ثابتة على كل حتة.'],
                ['كل يوم', 'تحصيل مع ضمان', 'فلوس الدفع عند الاستلام بتوصلك يومي بدل ما تفضل محبوسة عند شركة الشحن أسابيع.'],
            ] as $card)
                <div class="reveal rounded-2xl border border-jade-800/60 bg-jade-900/40 p-7 backdrop-blur">
                    <p class="text-foil text-4xl font-bold tracking-tight">{{ $card[0] }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ $card[1] }}</p>
                    <p class="mt-3 text-sm leading-relaxed text-jade-200/80">{{ $card[2] }}</p>
                </div>
            @endforeach
        </div>

        <div class="reveal mt-6 grid items-center gap-10 rounded-3xl border border-gold-500/25 bg-jade-900/30 p-7 backdrop-blur md:grid-cols-2 md:p-11">
            <div>
                <h3 class="text-2xl font-bold leading-snug tracking-tight md:text-3xl">
                    رأس مالك بيلف <span class="text-foil">أسرع بكتير</span>
                </h3>
                <p class="mt-4 leading-relaxed text-jade-200/85">
                    أكبر وجع في الدفع عند الاستلام مش الشحن — إنه فلوسك تقعد أسبوعين أو تلاتة
                    عند شركة الشحن. مع <span class="font-semibold text-gold-200">ضمان</span>،
                    تحصيلات كل يوم بتتحوّل لحسابك، فتشتري بضاعة تاني بدل ما تستنى.
                </p>

                <ul class="mt-7 space-y-3.5 text-sm">
                    @foreach ([
                        'تحصيل يومي بدل دورة توريد كل أسبوعين',
                        'كشف حساب واضح: كل طلب وفلوسه راحت فين',
                        'العمولة معلنة قبل ما تشحن — مافيش رسوم مخفية',
                    ] as $point)
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-gold-500/15 text-gold-300">
                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            <span class="text-jade-100/90">{{ $point }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="halo-gold relative rounded-2xl border border-jade-700/50 bg-jade-950/70 p-6">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-jade-300/70">تحويل النهارده</span>
                    <span class="rounded-full bg-success/15 px-2.5 py-1 text-[11px] font-medium text-success">تم التحويل</span>
                </div>

                <p class="text-foil mt-3 text-4xl font-bold tracking-tight md:text-5xl">
                    <span class="tabular">١٢٬٤٥٠</span><span class="mr-1 text-lg">ج.م</span>
                </p>

                <dl class="mt-6 space-y-3 border-t border-jade-800/70 pt-5 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-jade-300/70">٣٨ طلب اتسلّم</dt>
                        <dd class="tabular text-jade-100">١٣٬٢٠٠</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-jade-300/70">شحن وتحصيل</dt>
                        <dd class="tabular text-jade-100">−٧٥٠</dd>
                    </div>
                    <div class="flex justify-between border-t border-jade-800/70 pt-3 text-base font-semibold">
                        <dt>الصافي لحسابك</dt>
                        <dd class="tabular text-gold-300">١٢٬٤٥٠</dd>
                    </div>
                </dl>
            </div>
        </div>

        <p class="reveal mt-5 text-center text-xs text-jade-300/60">
            ربط ضمان بيتفعّل للمتاجر على دفعات — تقدر تطلب تفعيله من لوحة التحكم.
        </p>
    </div>
</section>

{{-- ══ 6. Pricing ═══════════════════════════════════════════════════════ --}}
<section id="pricing" class="relative py-20 md:py-28">
    <div class="mx-auto max-w-5xl px-4 md:px-6">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="reveal text-balance text-3xl font-bold leading-tight tracking-tight md:text-4xl">
                <span class="text-jade-gradient">أول ٣ شهور</span> الطلب بنص جنيه
            </h2>
            <p class="reveal mt-4 text-muted-foreground">
                مافيش اشتراك شهري، ومافيش نسبة من مبيعاتك. بتدفع على الطلب اللي دخلك بس — ونص جنيه زيادة لو اتبعت للعميل تأكيد على واتساب من رقمك، أو جنيه من رقم متجر برو الرسمي.
            </p>
        </div>

        <div class="mt-12 grid gap-5 md:grid-cols-2">
            <div class="reveal surface flex flex-col p-8">
                <p class="text-sm font-medium text-muted-foreground">أول ٣ شهور</p>
                <p class="mt-3 flex items-baseline gap-2">
                    <span class="tabular text-5xl font-bold tracking-tight text-primary">٠٫٥٠</span>
                    <span class="text-sm text-muted-foreground">ج.م / طلب</span>
                </p>
                <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                    و<span class="tabular">١٫٠٠</span> ج.م للطلب اللي اتبعتله تأكيد واتساب من رقمك (<span class="tabular">١٫٥٠</span> من رقم متجر برو الرسمي). كل المميزات، من غير حد على عدد الطلبات ولا المنتجات ولا الزيارات.
                </p>
                <a href="{{ route('register') }}" class="btn-primary mt-auto pt-3">ابدأ دلوقتي</a>
            </div>

            <div class="reveal surface-lux halo-gold relative flex flex-col p-8">
                <p class="text-sm font-medium text-muted-foreground">بعد كده</p>
                <p class="mt-3 flex items-baseline gap-2">
                    <span class="text-foil tabular text-5xl font-bold tracking-tight">٠٫٧٥</span>
                    <span class="text-sm text-muted-foreground">ج.م / طلب</span>
                </p>
                <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                    و<span class="tabular">١٫٢٥</span> ج.م بتأكيد واتساب من رقمك. متجر بيعمل ٥٠٠ طلب في الشهر وبيأكّدهم كلهم على واتساب بيدفع <span class="font-semibold text-foreground">٦٢٥ جنيه</span> — وبس.
                </p>

                <ul class="mt-6 space-y-2.5 text-sm text-muted-foreground">
                    @foreach (['مافيش اشتراك شهري', 'مافيش نسبة من المبيعات', 'مافيش رسوم على المنتجات ولا الزيارات'] as $point)
                        <li class="flex items-center gap-2">
                            <svg class="size-4 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- What it would cost THEM. A merchant does this sum in their head
             anyway; doing it here, with their own order count, is the moment
             "half a pound" stops being a slogan. The figures are the page's
             own: 1.25 an order with a WhatsApp confirmation, the most a merchant pays\n             after the first three months, against the ~2/order + ~5000/month and the
             ~1450/month in the table below. Renders the 500-order answer, so
             it is complete with no script. --}}
        <div class="reveal surface mt-5 p-6 md:p-8" data-calc>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <label for="calc-orders" class="text-[15px] font-semibold">بتعمل كام طلب في الشهر؟</label>
                    <p class="mt-1 text-[13px] text-muted-foreground">حرّك وشوف هتدفع كام بعد أول ٣ شهور، بتأكيد واتساب على كل طلب.</p>
                </div>
                <p class="tabular text-[28px] font-bold leading-none md:text-[34px]">
                    <span data-calc-orders>٥٠٠</span>
                    <span class="text-[15px] font-medium text-muted-foreground">طلب</span>
                </p>
            </div>

            <input id="calc-orders" type="range" min="50" max="5000" step="50" value="500"
                   class="mt-5 h-2 w-full cursor-pointer appearance-none rounded-full bg-foreground/[0.08] accent-primary"
                   aria-describedby="calc-result">

            <div id="calc-result" class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3" aria-live="polite">
                <div class="rounded-[14px] bg-primary/[0.07] p-4">
                    <p class="text-[13px] font-semibold text-primary">متجر برو</p>
                    <p class="tabular mt-1 text-[26px] font-bold leading-tight text-primary"><span data-calc-us>٦٢٥</span> <span class="text-[13px] font-medium">ج.م</span></p>
                </div>
                <div class="rounded-[14px] bg-foreground/[0.04] p-4">
                    <p class="text-[13px] text-muted-foreground">منصة ١</p>
                    <p class="tabular mt-1 text-[22px] font-semibold leading-tight text-muted-foreground">~<span data-calc-a>٦٬٠٠٠</span> <span class="text-[13px] font-medium">ج.م</span></p>
                </div>
                <div class="rounded-[14px] bg-foreground/[0.04] p-4">
                    <p class="text-[13px] text-muted-foreground">منصة ٢</p>
                    <p class="tabular mt-1 text-[22px] font-semibold leading-tight text-muted-foreground">~<span data-calc-b>١٬٤٥٠</span> <span class="text-[13px] font-medium">ج.م</span></p>
                </div>
            </div>
        </div>

        {{-- Our own number is a real number now, and it still wins by a margin
             wide enough that we do not need to round it in our favour. --}}
        <div class="reveal surface mt-8 overflow-x-auto">
            <table class="w-full min-w-[34rem] text-sm">
                <thead>
                    <tr class="border-b border-border">
                        <th class="p-4 text-right font-medium text-muted-foreground">&nbsp;</th>
                        <th class="bg-primary/5 p-4 text-center font-bold text-primary">متجر برو</th>
                        <th class="p-4 text-center font-medium text-muted-foreground">منصة ١</th>
                        <th class="p-4 text-center font-medium text-muted-foreground">منصة ٢</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ([
                        ['على كل طلب', '٠٫٧٥ ج.م (١٫٢٥ بتأكيد واتساب)', '~٢ ج.م', '—'],
                        ['اشتراك شهري', 'صفر', '~٥٠٠٠ ج.م', '~١٤٥٠ ج.م'],
                        ['شبكة شحن', '+٣٠٠ شركة', 'تكاملات', 'إضافات'],
                        ['تحصيل يومي', '✓ مع ضمان', '✗', '✗'],
                    ] as $row)
                        <tr>
                            <td class="p-4 font-medium">{{ $row[0] }}</td>
                            <td class="bg-primary/5 p-4 text-center font-semibold text-primary">{{ $row[1] }}</td>
                            <td class="p-4 text-center text-muted-foreground">{{ $row[2] }}</td>
                            <td class="p-4 text-center text-muted-foreground">{{ $row[3] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="reveal mt-4 text-center text-xs text-muted-foreground">
            الأرقام محوّلة من باقات معلنة على مواقع منصات متاح استخدامها في مصر، أغسطس ٢٠٢٦.
        </p>
    </div>
</section>

{{-- ══ 7. Features — a bento of small, working screens ══════════════════
     Each tile shows the feature instead of naming it: the orders table with
     rows in it, the WhatsApp message as a chat bubble, the domain as an
     address bar. A merchant recognises their own day in these faster than in
     a sentence, and every one is a screen that exists in the dashboard.

     Icon tiles are iOS app icons — a filled, softly lit square in the
     feature's own colour — rather than a pale glyph on a pale circle.
--}}
<section class="hairline-t hairline-b bg-card py-20 md:py-28">
    <div class="mx-auto max-w-6xl px-4 md:px-6">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="reveal text-balance text-3xl font-bold md:text-5xl">
                كل اللي محتاجه في مكان واحد
            </h2>
            <p class="reveal mt-4 text-[17px] text-muted-foreground">
                مش قايمة وعود — دي شاشات شغّالة دلوقتي في لوحة التحكم.
            </p>
        </div>

        @php
            // One lit square per feature, like a home-screen icon.
            $appIcon = fn (string $from, string $to) => "flex size-12 shrink-0 items-center justify-center rounded-[14px] bg-gradient-to-b text-white shadow-[inset_0_1px_0_hsl(0_0%_100%/0.25),0_4px_12px_-4px_hsl(0_0%_0%/0.35)] {$from} {$to}";
        @endphp

        <div class="mt-14 grid grid-cols-1 gap-4 md:grid-cols-6 md:gap-5">
            {{-- Orders table: the one that decides the sale, widest tile. --}}
            <div class="reveal surface overflow-hidden p-6 md:col-span-4 md:p-8">
                <div class="flex items-start gap-4">
                    <span class="{{ $appIcon('from-[hsl(160_60%_40%)]', 'to-[hsl(165_75%_26%)]') }}">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18M9 21V9"/></svg>
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-title-3">جدول طلبات زي الإكسل</h3>
                        <p class="mt-1 text-[15px] leading-relaxed text-muted-foreground">
                            عدّل أي خانة بضغطة، غيّر حالة طلبات كتير دفعة واحدة، اطبع البوالص، وصدّر إكسل.
                            وكل طلب بيوريك العميل استلم كام مرة ورفض كام مرة.
                        </p>
                    </div>
                </div>

                <div class="relative mt-6 overflow-hidden rounded-[14px] bg-background shadow-[0_0_0_0.5px_hsl(var(--foreground)/0.1)]">
                    <div class="grid grid-cols-[1.5rem_1fr_auto_auto] gap-x-4 px-4 py-2.5 text-[11px] font-semibold text-muted-foreground">
                        <span></span><span>العميل</span><span>الإجمالي</span><span>الحالة</span>
                    </div>
                    @foreach ([
                        ['سارة عبد الله', 'القاهرة', '٧٩٨', 'قيد المراجعة', 'badge-warning', true],
                        ['أحمد مصطفى', 'الجيزة', '١٬٢٥٠', 'تم التأكيد', 'badge-info', true],
                        ['منى إبراهيم', 'الإسكندرية', '٣٩٩', 'تم الشحن', 'badge-info', false],
                        ['كريم حسن', 'المنصورة', '٨٥٥', 'تم التوصيل', 'badge-success', false],
                    ] as [$name, $city, $total, $status, $badge, $picked])
                        <div @class(['grid grid-cols-[1.5rem_1fr_auto_auto] items-center gap-x-4 border-t-[0.5px] border-foreground/10 px-4 py-2.5 text-[13px]', 'bg-primary/[0.06]' => $picked])>
                            <span @class(['flex size-4 items-center justify-center rounded-[5px]', 'bg-primary text-white' => $picked, 'shadow-[inset_0_0_0_1.5px_hsl(var(--foreground)/0.25)]' => ! $picked])>
                                @if ($picked)<svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>@endif
                            </span>
                            <span class="min-w-0 truncate"><b class="font-semibold">{{ $name }}</b> <span class="text-muted-foreground">· {{ $city }}</span></span>
                            <span class="tabular font-semibold">{{ $total }}</span>
                            <span class="{{ $badge }} !text-[10px]">{{ $status }}</span>
                        </div>
                    @endforeach

                    {{-- The bulk bar, as it appears once rows are ticked. --}}
                    <div class="material hairline-t flex flex-wrap items-center gap-2 px-4 py-2.5 text-[12px]">
                        <span class="font-semibold"><span class="tabular">٢</span> متحدد</span>
                        <span class="rounded-full bg-primary px-3 py-1 font-semibold text-primary-foreground">تم الشحن</span>
                        <span class="rounded-full bg-foreground/[0.07] px-3 py-1 font-medium">اطبع البوالص</span>
                        <span class="ms-auto text-muted-foreground">Excel ⤓</span>
                    </div>
                </div>
            </div>

            {{-- WhatsApp: the message as it lands on the customer's phone. --}}
            <div class="reveal surface flex flex-col overflow-hidden p-6 md:col-span-2">
                <span class="{{ $appIcon('from-[hsl(142_70%_48%)]', 'to-[hsl(150_75%_34%)]') }}">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </span>
                <h3 class="mt-4 text-[19px] font-semibold">تأكيد واتساب</h3>
                <p class="mt-1 text-[15px] leading-relaxed text-muted-foreground">رسالة جاهزة بكل تفاصيل الطلب، بضغطة.</p>

                <div class="mt-5 space-y-2 rounded-[16px] bg-[hsl(40_30%_92%)] p-3 text-[12px] leading-relaxed dark:bg-foreground/10">
                    <div class="me-6 rounded-[14px] rounded-se-[4px] bg-[hsl(110_55%_86%)] px-3 py-2 text-[hsl(160_30%_12%)] shadow-sm">
                        أهلاً سارة 👋 طلبك رقم <span class="tabular">#١٢</span> وصلنا:<br>
                        • ٢× قميص قطن (أبيض · L)<br>
                        الإجمالي: ٧٩٨ ج.م — الدفع عند الاستلام. نأكّد الطلب؟
                        <span class="mt-0.5 block text-left text-[10px] text-[hsl(160_20%_35%)]" dir="ltr">10:42 ✓✓</span>
                    </div>
                    <div class="ms-10 w-fit rounded-[14px] rounded-ss-[4px] bg-white px-3 py-2 text-[hsl(160_30%_12%)] shadow-sm">
                        أيوه أكّد 🙏
                    </div>
                </div>
            </div>

            {{-- Domain + certificate, one address bar. --}}
            <div class="reveal surface p-6 md:col-span-2">
                <span class="{{ $appIcon('from-[hsl(210_85%_58%)]', 'to-[hsl(220_80%_44%)]') }}">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>
                </span>
                <h3 class="mt-4 text-[19px] font-semibold">دومينك وشهادة الأمان</h3>
                <p class="mt-1 text-[15px] leading-relaxed text-muted-foreground">
                    نقولك تحط إيه في لوحة الدومين، والقفل الأخضر بيتظبط ويتجدد لوحده.
                </p>
                <div class="mt-5 flex items-center gap-2 rounded-full bg-foreground/[0.05] px-3 py-2 text-[13px]" dir="ltr">
                    <svg class="size-3.5 text-success" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 10V8a5 5 0 0 1 10 0v2h1a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1zm2 0h6V8a3 3 0 0 0-6 0z"/></svg>
                    <span class="font-medium">mahmoud.com</span>
                    <span class="ms-auto rounded-full bg-success/15 px-2 py-0.5 text-[10.5px] font-semibold text-success">SSL</span>
                </div>
            </div>

            {{-- Pixels: the events, counted. --}}
            <div class="reveal surface p-6 md:col-span-2">
                <span class="{{ $appIcon('from-[hsl(265_70%_62%)]', 'to-[hsl(270_60%_46%)]') }}">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 20V10M18 20V4M6 20v-4"/></svg>
                </span>
                <h3 class="mt-4 text-[19px] font-semibold">بيكسل و CAPI</h3>
                <p class="mt-1 text-[15px] leading-relaxed text-muted-foreground">فيسبوك وتيك توك وسناب، والشراء بيتبعت من السيرفر كمان.</p>
                <div class="mt-5 space-y-2 text-[12px]">
                    @foreach ([['شاف المنتج', 100], ['بدأ الطلب', 46], ['اشترى', 21]] as [$label, $w])
                        <div class="flex items-center gap-2">
                            <span class="w-16 shrink-0 text-muted-foreground">{{ $label }}</span>
                            <span class="h-2 flex-1 overflow-hidden rounded-full bg-foreground/[0.06]">
                                <span class="block h-full rounded-full bg-gradient-to-l from-[hsl(265_70%_62%)] to-[hsl(205_80%_60%)]" style="width: {{ $w }}%"></span>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Themes: the swatches themselves. --}}
            <div class="reveal surface p-6 md:col-span-2">
                <span class="{{ $appIcon('from-[hsl(25_90%_60%)]', 'to-[hsl(10_80%_50%)]') }}">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18c1 0 1.5-.8 1.5-1.6 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.2 0-.9.7-1.6 1.6-1.6H16a5 5 0 0 0 5-5c0-4-4-7.4-9-7.4z"/><circle cx="7.5" cy="11" r="1"/><circle cx="10" cy="7" r="1"/><circle cx="15" cy="7.5" r="1"/></svg>
                </span>
                <h3 class="mt-4 text-[19px] font-semibold">تسع ثيمات جاهزة</h3>
                <p class="mt-1 text-[15px] leading-relaxed text-muted-foreground">كل ثيم بخطه وشكل كروته — ولكل واحد متجر معرض حي.</p>
                <div class="mt-5 flex -space-x-2 space-x-reverse">
                    @foreach (config('themes.themes') as $swatch)
                        <span class="flex size-9 items-center justify-center rounded-full text-[13px] font-bold ring-2 ring-card"
                              style="background: hsl({{ $swatch['palette']['primary'] }}); color: hsl({{ $swatch['palette']['primary-foreground'] }})"
                              title="{{ $swatch['name'] }}">أ</span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ══ 8. Catalogue everywhere ══════════════════════════════════════════
     Shipped: the feed is live on every store's own domain and the settings
     screen walks a merchant through each platform. The label says so, because
     an unmarked "coming soon" next to features they can verify in five
     minutes is what makes them doubt the rest of the page.

     Drawn as what it is: one list of products, synced out to four places.
--}}
<section class="relative overflow-hidden py-20 md:py-28">
    <div class="mx-auto max-w-6xl px-4 md:px-6">
        <div class="grid items-center gap-12 lg:grid-cols-2">
            <div>
                <span class="reveal badge-success mb-5">شغّال دلوقتي</span>
                <h2 class="reveal text-balance text-3xl font-bold leading-tight md:text-5xl">
                    منتجاتك تعرض نفسها في
                    <span class="text-jade-gradient">كل مكان</span>
                </h2>
                <p class="reveal mt-5 text-[17px] leading-relaxed text-muted-foreground">
                    ارفع منتجاتك مرة واحدة، وإحنا نطلّعها كاتالوج جاهز لجوجل وفيسبوك
                    وإنستجرام وتيك توك — من غير ما ترفعها من الأول في كل منصة.
                </p>

                <ul class="reveal mt-7 space-y-3.5 text-[15px]">
                    @foreach ([
                        'ملف منتجات (Feed) بيتحدّث لوحده مع كل تعديل في السعر أو المخزون',
                        'المنتج اللي خلص من المخزون بيتشال من الكاتالوج أوتوماتيك',
                        'إعلانات الكتالوج الديناميكية تشتغل من غير رفع يدوي',
                    ] as $point)
                        <li class="flex items-start gap-3">
                            <svg class="mt-1 size-4 shrink-0 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            <span>{{ $point }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="reveal relative">
                {{-- The feed: real products, each marked as synced. --}}
                <div class="surface mx-auto max-w-md overflow-hidden">
                    <div class="flex items-center justify-between px-4 pb-2 pt-4">
                        <p class="text-[15px] font-semibold">ملف المنتجات</p>
                        <span class="flex items-center gap-1.5 text-[12px] font-medium text-success">
                            <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-success/60 motion-reduce:animate-none"></span><span class="relative inline-flex size-2 rounded-full bg-success"></span></span>
                            بيتحدّث لوحده
                        </span>
                    </div>
                    <div class="ios-group !rounded-none !shadow-none">
                        @foreach ([['shirt', 'قميص قطن مصري', '٣٩٩', true], ['watch', 'ساعة جلد كلاسيك', '١٬٢٥٠', true], ['shoe', 'حذاء رياضي أبيض', '٨٩٠', true], ['bag', 'شنطة جلد', '٦٥٠', false]] as [$kind, $name, $price, $inStock])
                            <div class="ios-row">
                                <img src="/images/landing/{{ $kind }}.jpg" alt="" loading="lazy" decoding="async" width="400" height="400" class="size-11 shrink-0 rounded-[10px] object-cover">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[15px] font-medium">{{ $name }}</p>
                                    <p class="text-[12px] text-muted-foreground"><span class="tabular">{{ $price }}</span> ج.م</p>
                                </div>
                                @if ($inStock)
                                    <span class="rounded-full bg-success/10 px-2 py-0.5 text-[11px] font-semibold text-success">متزامن</span>
                                @else
                                    <span class="rounded-full bg-foreground/[0.06] px-2 py-0.5 text-[11px] font-semibold text-muted-foreground">خلص — اتشال</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Where it goes: four app icons under it. --}}
                <div class="mx-auto mt-5 grid max-w-md grid-cols-4 gap-3 text-center text-[11px] font-medium text-muted-foreground">
                    @foreach ([
                        ['Google', 'bg-white text-[hsl(217_89%_51%)] shadow-[0_0_0_0.5px_hsl(var(--foreground)/0.12)]', 'M12 2a10 10 0 1 0 10 10h-10z'],
                        ['فيسبوك', 'bg-gradient-to-b from-[hsl(214_89%_58%)] to-[hsl(220_85%_46%)] text-white', 'M15 3h-2.5A4.5 4.5 0 0 0 8 7.5V10H6v3.5h2V21h3.5v-7.5H14l.5-3.5h-3V8a1 1 0 0 1 1-1H15z'],
                        ['إنستجرام', 'bg-gradient-to-tr from-[hsl(40_95%_60%)] via-[hsl(340_80%_55%)] to-[hsl(275_70%_55%)] text-white', 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zm5 5a4 4 0 1 0 0 8 4 4 0 0 0 0-8z'],
                        ['تيك توك', 'bg-[hsl(0_0%_8%)] text-white', 'M21 8a5 5 0 0 1-5-5h-4v13a3 3 0 1 1-3-3'],
                    ] as [$label, $tile, $icon])
                        <div class="flex flex-col items-center gap-1.5">
                            <span class="flex size-12 items-center justify-center rounded-[14px] {{ $tile }}">
                                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                            </span>
                            {{ $label }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ══ 9. How it works ══════════════════════════════════════════════════
     Three steps on one line joined by a rule, each with the screen it ends
     on — so "two lines to a first order" is something you can see, not take
     on trust.
--}}
<section class="py-20 md:py-28">
    <div class="mx-auto max-w-5xl px-4 md:px-6">
        <h2 class="reveal text-center text-balance text-3xl font-bold md:text-5xl">
            من سطرين لأول طلب
        </h2>

        <div class="relative mt-14 grid grid-cols-1 gap-5 md:grid-cols-3">
            <div class="pointer-events-none absolute inset-x-[16%] top-5 hidden h-px bg-gradient-to-l from-transparent via-foreground/15 to-transparent md:block"></div>

            {{-- 1. Describe --}}
            <div class="reveal relative flex flex-col items-center text-center">
                <span class="relative z-10 flex size-10 items-center justify-center rounded-full bg-primary text-[15px] font-bold text-primary-foreground ring-8 ring-background"><span class="tabular">١</span></span>
                <h3 class="mt-4 text-[19px] font-semibold">احكي عن مشروعك</h3>
                <p class="mt-1.5 text-[15px] leading-relaxed text-muted-foreground">بتبيع إيه، ولمين، وعايز الشكل إزاي.</p>
                <div class="ai-ring mt-5 w-full max-w-[16rem] rounded-[18px]">
                    <div class="rounded-[16.5px] bg-card px-3.5 py-3 text-start text-[12px] leading-relaxed">
                        <span class="text-muted-foreground">بتبيع إيه؟</span><br>
                        عطور شرقية وبخور، وعايز الشكل فخم وهادي.
                    </div>
                </div>
            </div>

            {{-- 2. Products --}}
            <div class="reveal relative flex flex-col items-center text-center">
                <span class="relative z-10 flex size-10 items-center justify-center rounded-full bg-primary text-[15px] font-bold text-primary-foreground ring-8 ring-background"><span class="tabular">٢</span></span>
                <h3 class="mt-4 text-[19px] font-semibold">ضيف منتجاتك</h3>
                <p class="mt-1.5 text-[15px] leading-relaxed text-muted-foreground">ارفع الصور، وسيب الاسم والوصف علينا.</p>
                <div class="surface mt-5 flex w-full max-w-[16rem] items-center gap-3 p-2.5 text-start">
                    <img src="/images/landing/watch.jpg" alt="" loading="lazy" decoding="async" width="400" height="400" class="size-12 shrink-0 rounded-[10px] object-cover">
                    <div class="min-w-0 flex-1 space-y-1.5">
                        <p class="truncate text-[12px] font-semibold">ساعة جلد كلاسيك</p>
                        <span class="ai-shimmer block h-2 w-full rounded-full"></span>
                        <span class="ai-shimmer block h-2 w-2/3 rounded-full"></span>
                    </div>
                </div>
            </div>

            {{-- 3. Share --}}
            <div class="reveal relative flex flex-col items-center text-center">
                <span class="relative z-10 flex size-10 items-center justify-center rounded-full bg-primary text-[15px] font-bold text-primary-foreground ring-8 ring-background"><span class="tabular">٣</span></span>
                <h3 class="mt-4 text-[19px] font-semibold">انشر وشارك اللينك</h3>
                <p class="mt-1.5 text-[15px] leading-relaxed text-muted-foreground">في إعلان فيسبوك أو تيك توك، والطلبات تبدأ.</p>
                <div class="surface mt-5 w-full max-w-[16rem] p-3 text-start">
                    <div class="flex items-center gap-2.5">
                        <span class="flex size-8 items-center justify-center rounded-full bg-success/15 text-success">
                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[12px] font-semibold">طلب جديد <span class="tabular">#١</span></p>
                            <p class="text-[11px] text-muted-foreground">سارة · القاهرة · <span class="tabular">٧٩٨</span> ج.م</p>
                        </div>
                        <span class="text-[10px] text-muted-foreground">دلوقتي</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ══ 10. Close ═════════════════════════════════════════════════════════ --}}
<section class="px-4 pb-20 md:px-6 md:pb-28">
    <div class="mesh-ink relative mx-auto max-w-5xl overflow-hidden rounded-[2rem] px-6 py-16 text-center text-jade-50 md:py-24">
        <h2 class="reveal text-balance text-3xl font-bold leading-tight md:text-5xl">
            احكي عن مشروعك،
            <span class="text-foil">والباقي علينا</span>
        </h2>
        <p class="reveal mt-4 text-[17px] text-jade-200/85">
            أول ٣ شهور الطلب بنص جنيه. من غير اشتراك ولا كارت ائتمان.
        </p>
        <a href="{{ route('register') }}" class="reveal btn mt-8 !min-h-[52px] !rounded-full bg-white !px-9 !text-[17px] text-jade-900 hover:bg-jade-50">
            جهّز متجرك مجانًا
        </a>
    </div>
</section>

@push('scripts')
<script>
/*
 * Scroll reveal.
 *
 * Elements are authored hidden, so anything that stops this script from running
 * would leave a blank page. Bail out to the visible state first, then animate —
 * the page must never depend on JS to have content.
 */
(function () {
    const items = document.querySelectorAll('.reveal');

    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        items.forEach((el) => el.classList.add('shown'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, i) => {
            if (!entry.isIntersecting) return;
            // Stagger within a batch so a row of cards arrives in sequence
            // rather than snapping in together.
            setTimeout(() => entry.target.classList.add('shown'), i * 70);
            observer.unobserve(entry.target);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

    items.forEach((el) => observer.observe(el));
})();

/*
 * The hero demo: type the merchant's two lines, then let the store assemble.
 *
 * The HTML already holds the finished state, so a failed or blocked script
 * shows a complete demo, not an empty card. This only hides the pieces and
 * plays them back in, once, when the card is on screen.
 */
(function () {
    const demo = document.querySelector('[data-ai-demo]');
    if (!demo || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const typed = demo.querySelector('[data-ai-typed]');
    const status = demo.querySelector('[data-ai-status]');
    const steps = [...demo.querySelectorAll('[data-ai-step]')];
    const replay = demo.querySelector('[data-ai-replay]');
    const text = typed.textContent;
    const finalStatus = status.textContent;
    const wait = (ms) => new Promise((r) => setTimeout(r, ms));

    steps.forEach((s) => {
        s.style.transition = 'opacity .5s var(--ease-ios), transform .5s var(--ease-ios)';
    });

    const hideAll = () => steps.forEach((s) => {
        s.style.opacity = '0';
        s.style.transform = 'translateY(6px)';
    });

    let running = false;

    async function play() {
        if (running) return;
        running = true;
        replay.hidden = true;
        hideAll();

        typed.textContent = '';
        status.textContent = 'بيكتب…';
        for (let i = 1; i <= text.length; i++) {
            typed.textContent = text.slice(0, i);
            await wait(26);
        }

        for (const line of ['بيختار الشكل…', 'بيعمل الأقسام…', 'بيكتب الصفحة الرئيسية…']) {
            status.textContent = line;
            await wait(650);
        }

        for (const step of steps) {
            step.style.opacity = '1';
            step.style.transform = 'none';
            await wait(220);
        }

        status.textContent = finalStatus;
        replay.hidden = false;
        running = false;
    }

    replay.addEventListener('click', play);

    if ('IntersectionObserver' in window) {
        hideAll();
        new IntersectionObserver(([entry], observer) => {
            if (!entry.isIntersecting) return;
            observer.disconnect();
            play();
        }, { threshold: 0.4 }).observe(demo);
    }
})();

/*
 * Pricing calculator. Same three formulas as the table under it, in Arabic
 * digits like the rest of the page.
 */
(function () {
    const calc = document.querySelector('[data-calc]');
    if (!calc) return;

    const input = calc.querySelector('input[type=range]');
    const fmt = (n) => Math.round(n).toLocaleString('ar-EG');
    const set = (key, value) => (calc.querySelector(`[data-calc-${key}]`).textContent = fmt(value));

    const update = () => {
        const orders = Number(input.value);
        set('orders', orders);
        set('us', orders * 1.25);
        set('a', orders * 2 + 5000);
        set('b', 1450);
    };

    input.addEventListener('input', update);
    update();
})();
</script>
@endpush

@endsection

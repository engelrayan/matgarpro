@extends('marketing.layout')

@section('title', 'متجر برو — متجرك الإلكتروني جاهز في ٥ دقائق')
@section('description', 'أنشئ متجرك الإلكتروني بالعربي من غير أكواد. ٣ شهور مجانية وبعدها ٥٠ قرش على الطلب. شحن مع أكتر من ٣٠٠ شركة عبر شبكة سندباد، وفلوس التحصيل توصلك كل يوم مع ضمان.')

@section('content')

{{-- ══ 1. Hero ══════════════════════════════════════════════════════════
     A framed panel rather than a full-bleed band. The frame is what makes the
     top of the page read as a designed surface instead of a page that simply
     starts — and it gives the floating pills an edge to hang off.
--}}
<section class="px-4 pt-4 md:px-6 md:pt-6">
    <div class="mesh relative mx-auto max-w-[88rem] overflow-hidden rounded-[2rem] border border-gold-200/50 shadow-e2">
        <div class="checks pointer-events-none absolute inset-0"></div>

        <div class="relative px-6 pb-16 pt-14 md:pb-20 md:pt-20">
            <div class="relative mx-auto max-w-3xl text-center">

                {{-- Pills, both flanks. Icons rather than bare text: a merchant
                     scans these before they read the sentence. --}}
                <div class="pointer-events-none absolute inset-0 hidden xl:block">
                    @foreach ([
                        ['top-4 -right-52', 'M5 12h14M12 5l7 7-7 7', 'شحن مع +٣٠٠ شركة', '0s'],
                        ['top-36 -right-64', 'M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6', 'تحصيل يومي', '-1.5s'],
                        ['bottom-8 -right-48', 'M3 3h18v18H3zM3 9h18M9 21V9', 'جدول طلبات', '-3s'],
                        ['top-4 -left-52', 'M3 9 12 2l9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z', 'دومينك الخاص', '-0.8s'],
                        ['top-36 -left-64', 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z', 'تأكيد واتساب', '-2.2s'],
                        ['bottom-8 -left-48', 'M12 20V10M18 20V4M6 20v-4', 'بيكسل و CAPI', '-3.8s'],
                    ] as [$pos, $icon, $label, $delay])
                        <span
                            class="floaty absolute {{ $pos }} flex items-center gap-2 whitespace-nowrap rounded-full border border-gold-200/70 bg-card/90 px-4 py-2 text-xs font-medium shadow-e2 backdrop-blur"
                            style="animation-delay: {{ $delay }}"
                        >
                            <svg class="size-3.5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                            {{ $label }}
                        </span>
                    @endforeach
                </div>

                <div class="reveal mx-auto mb-7 w-fit">
                    @include('partials.logo', ['class' => 'size-14 text-primary drop-shadow-lg'])
                </div>

                <span class="badge-gold reveal mb-6">٣ شهور مجانية بالكامل</span>

                <h1 class="reveal text-balance text-[2.6rem] font-bold leading-[1.12] tracking-tight md:text-6xl lg:text-7xl">
                    متجرك الإلكتروني جاهز في
                    <span class="text-jade-gradient">٥ دقائق</span>
                </h1>

                <p class="reveal mx-auto mt-6 max-w-xl text-balance text-lg leading-relaxed text-muted-foreground md:text-xl">
                    من غير أكواد ولا مبرمج. اكتب اسم متجرك وضيف منتجاتك،
                    وإحنا نتكفّل بالشحن والتحصيل.
                </p>

                <div class="reveal mt-9 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ route('register') }}" class="btn-primary sheen px-8 py-4 text-base shadow-e3">
                        ابدأ متجرك دلوقتي
                    </a>
                    <a href="#shipping" class="btn-outline bg-card/80 px-8 py-4 text-base backdrop-blur">
                        شوف الشحن والتحصيل
                    </a>
                </div>

                <p class="reveal mt-5 text-sm text-muted-foreground">
                    من غير كارت ائتمان · متجرك يفضل ليك · تلغي في أي وقت
                </p>
            </div>

            <div class="reveal mx-auto mt-16 max-w-4xl md:mt-20">
                @include('marketing.partials.mockup')
            </div>
        </div>

        {{-- Feature ticker along the panel's floor. Rendered twice so the loop
             is seamless; the copy is duplicated in markup rather than cloned in
             JS so it is there on first paint. --}}
        <div class="ticker relative overflow-hidden border-t border-gold-200/50 bg-card/60 py-4 backdrop-blur">
            <div class="ticker-track flex w-max gap-10">
                @for ($pass = 0; $pass < 2; $pass++)
                    @foreach ([
                        ['M3 3h18v18H3zM3 9h18M9 21V9', 'جدول طلبات زي الإكسل'],
                        ['M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z', 'تأكيد واتساب'],
                        ['M12 20V10M18 20V4M6 20v-4', 'بيكسل و Conversions API'],
                        ['M5 12h14M12 5l7 7-7 7', 'شحن مع +٣٠٠ شركة'],
                        ['M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6', 'تحصيل يومي مع ضمان'],
                        ['M3 9 12 2l9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z', 'دومينك الخاص بـ SSL'],
                        ['M4 4h16v6H4zM4 14h16v6H4z', 'تسع ثيمات جاهزة'],
                        ['M20 6 9 17l-5-5', 'الدفع عند الاستلام'],
                        ['M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4zM3 6h18', 'بوليصة شحن للطباعة'],
                        ['M12 2v20M2 12h20', 'كاتالوج ميتا وجوجل وتيك توك'],
                    ] as [$icon, $label])
                        <span class="flex shrink-0 items-center gap-2 text-sm text-muted-foreground" @if ($pass) aria-hidden="true" @endif>
                            <svg class="size-4 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                            {{ $label }}
                        </span>
                    @endforeach
                @endfor
            </div>
        </div>
    </div>
</section>

{{-- ══ 2. Proof strip ═══════════════════════════════════════════════════ --}}
<section class="border-y border-border bg-card">
    <div class="mx-auto grid max-w-6xl grid-cols-2 divide-x divide-x-reverse divide-border px-6 md:grid-cols-4">
        @foreach ([
            ['+٣٠٠', 'شركة شحن'],
            ['كل يوم', 'تحصيل فلوسك'],
            ['٥٠ قرش', 'على الطلب — بعد ٣ شهور'],
            ['٥ دقائق', 'من التسجيل لأول منتج'],
        ] as $stat)
            <div class="reveal px-4 py-7 text-center">
                <p class="text-foil text-2xl font-bold tracking-tight md:text-3xl">{{ $stat[0] }}</p>
                <p class="mt-1.5 text-xs text-muted-foreground md:text-sm">{{ $stat[1] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- ══ 3. The store builder ═════════════════════════════════════════════ --}}
{{-- Placed straight after the proof strip: "أنت اللي بتبني شكل متجرك" is the
     answer to the first objection a merchant has after the numbers — whether
     their shop will look like everybody else's. Self-contained, so it can be
     moved or removed by touching this one line. --}}
@include('marketing.partials.builder-showcase')

{{-- ══ 4. Shipping & Daman — the reason to pick us ══════════════════════ --}}
<section id="shipping" class="mesh-ink relative overflow-hidden py-20 text-jade-50 md:py-28">
    <div class="relative mx-auto max-w-6xl px-6">
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

{{-- ══ 4. Pricing ═══════════════════════════════════════════════════════ --}}
<section id="pricing" class="relative py-20 md:py-28">
    <div class="mx-auto max-w-5xl px-6">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="reveal text-balance text-3xl font-bold leading-tight tracking-tight md:text-4xl">
                <span class="text-jade-gradient">٣ شهور مجانية</span>، وبعدها نص جنيه على الطلب
            </h2>
            <p class="reveal mt-4 text-muted-foreground">
                مافيش اشتراك شهري، ومافيش نسبة من مبيعاتك. بتدفع على الطلب اللي دخلك بس.
            </p>
        </div>

        <div class="mt-12 grid gap-5 md:grid-cols-2">
            <div class="reveal surface flex flex-col p-8">
                <p class="text-sm font-medium text-muted-foreground">أول ٣ شهور</p>
                <p class="mt-3 text-5xl font-bold tracking-tight text-primary">مجانًا</p>
                <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                    كل المميزات، من غير حد على عدد الطلبات ولا المنتجات ولا الزيارات.
                </p>
                <a href="{{ route('register') }}" class="btn-primary mt-auto pt-3">ابدأ دلوقتي</a>
            </div>

            <div class="reveal surface-lux halo-gold relative flex flex-col p-8">
                <p class="text-sm font-medium text-muted-foreground">بعد كده</p>
                <p class="mt-3 flex items-baseline gap-2">
                    <span class="text-foil tabular text-5xl font-bold tracking-tight">٠٫٥٠</span>
                    <span class="text-sm text-muted-foreground">ج.م / طلب</span>
                </p>
                <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                    متجر بيعمل ٥٠٠ طلب في الشهر بيدفع <span class="font-semibold text-foreground">٢٥٠ جنيه</span> — وبس.
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
                        ['على كل طلب', '٠٫٥٠ ج.م', '~٢ ج.م', '—'],
                        ['اشتراك شهري', 'صفر', '~٥٠٠٠ ج.م', '~١٤٥٠ ج.م'],
                        ['فترة مجانية', '٣ شهور', 'باقة محدودة', 'أسبوعين'],
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

{{-- ══ 5. Features — a bento, not a uniform grid ════════════════════════ --}}
<section class="border-y border-border bg-card py-20 md:py-28">
    <div class="mx-auto max-w-6xl px-6">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="reveal text-balance text-3xl font-bold tracking-tight md:text-4xl">
                كل اللي محتاجه في مكان واحد
            </h2>
            <p class="reveal mt-4 text-muted-foreground">
                مش قايمة وعود — دي حاجات مبنية وشغّالة دلوقتي.
            </p>
        </div>

        <div class="mt-14 grid gap-4 md:grid-cols-3">
            {{-- Lead tile, double width: the feature that decides the sale. --}}
            <div class="reveal surface md:col-span-2 md:row-span-2 flex flex-col justify-between overflow-hidden p-8">
                <div>
                    <span class="badge-gold">الأهم</span>
                    <h3 class="mt-4 text-2xl font-bold tracking-tight">جدول طلبات زي الإكسل</h3>
                    <p class="mt-3 max-w-md leading-relaxed text-muted-foreground">
                        عدّل أي خانة بضغطة، حدّد طلبات وغيّر حالتها دفعة واحدة، اطبع البوالص،
                        وصدّر إكسل. وكل طلب بيوريك سجل العميل: كام مرة استلم وكام مرة رفض.
                    </p>
                </div>

                <div class="mt-7 overflow-hidden rounded-xl border border-border">
                    <div class="grid grid-cols-[auto_1fr_auto_auto] gap-x-4 bg-muted/50 px-4 py-2 text-[11px] font-medium text-muted-foreground">
                        <span>#</span><span>العميل</span><span>الإجمالي</span><span>الحالة</span>
                    </div>
                    @foreach ([
                        ['١٢', 'سارة عبد الله', '٧٩٨', 'قيد المراجعة', 'badge-warning'],
                        ['١١', 'أحمد مصطفى', '١٬٢٥٠', 'تم الشحن', 'badge-info'],
                        ['١٠', 'منى إبراهيم', '٣٩٩', 'تم التوصيل', 'badge-success'],
                    ] as $row)
                        <div class="grid grid-cols-[auto_1fr_auto_auto] items-center gap-x-4 border-t border-border px-4 py-2.5 text-xs">
                            <span class="tabular font-semibold">{{ $row[0] }}</span>
                            <span class="truncate">{{ $row[1] }}</span>
                            <span class="tabular">{{ $row[2] }}</span>
                            <span class="{{ $row[4] }} !text-[10px]">{{ $row[3] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- WhatsApp confirmation gets a wide tile with the actual message:
                 it is the step that decides whether a COD parcel is worth
                 shipping, and merchants recognise it instantly. --}}
            <div class="reveal surface md:col-span-1 overflow-hidden p-6">
                <span class="flex size-11 items-center justify-center rounded-xl bg-success/10 text-success">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                </span>
                <h3 class="mt-4 font-semibold">تأكيد واتساب</h3>
                <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                    رسالة جاهزة بكل تفاصيل الطلب — بضغطة بدل ما تكتبها ٤٠ مرة في اليوم.
                </p>

                <div class="mt-4 rounded-xl rounded-bl-sm bg-success/10 p-3 text-[11px] leading-relaxed">
                    أهلاً سارة 👋<br>
                    طلبك رقم <span class="tabular">#١٢</span> وصلنا:<br>
                    • ٢× قميص قطن (أبيض · L)<br>
                    الإجمالي: ٧٩٨ ج.م — الدفع عند الاستلام<br>
                    نأكّد الطلب؟
                </div>
            </div>

            @foreach ([
                ['M3 9 12 2l9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z', 'دومينك الخاص', 'اكتب دومينك، نقولك تحط إيه في لوحة الدومين بتاعتك، وخلاص.'],
                // Its own card now, not a half-sentence inside the domain one.
                // The padlock is the single most common reason a cash-on-delivery
                // buyer closes the tab, and it is the part merchants assume
                // costs money and needs a technician.
                ['M6 11V8a6 6 0 1 1 12 0v3M5 11h14v10H5z', 'شهادة أمان مجانية', 'القفل الأخضر بيتظبط لوحده أول ما الدومين يشتغل، وبيتجدد لوحده. مفيش شراء ولا مبرمج.'],
                ['M12 20V10M18 20V4M6 20v-4', 'بيكسل و CAPI', 'فيسبوك وتيك توك وسناب، والشراء بيتبعت من السيرفر كمان.'],
                ['M4 4h16v6H4zM4 14h16v6H4z', 'تسع ثيمات', 'كل ثيم بخطه وشكل هيدره وكروته — مش لون مختلف بس. ولكل واحد معرض حي بمنتجات.'],
            ] as [$icon, $title, $body])
                <div class="reveal surface p-6">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                    </span>
                    <h3 class="mt-4 font-semibold">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══ 6. Catalogue everywhere ══════════════════════════════════════════
     Shipped: the feed is live on every store's own domain and the settings
     screen walks a merchant through each platform. The label says so, because
     an unmarked "coming soon" next to features they can verify in five
     minutes is what makes them doubt the rest of the page.
--}}
<section class="relative py-20 md:py-28">
    <div class="mx-auto max-w-6xl px-6">
        <div class="grid items-center gap-12 lg:grid-cols-2">
            <div>
                <span class="reveal badge-success mb-5">شغّال دلوقتي</span>
                <h2 class="reveal text-balance text-3xl font-bold leading-tight tracking-tight md:text-4xl">
                    منتجاتك تعرض نفسها في
                    <span class="text-jade-gradient">كل مكان</span>
                </h2>
                <p class="reveal mt-5 leading-relaxed text-muted-foreground">
                    ارفع منتجاتك مرة واحدة، وإحنا نطلّعها كاتالوج جاهز لجوجل وفيسبوك
                    وإنستجرام وتيك توك — من غير ما ترفعها من الأول في كل منصة.
                </p>

                <ul class="reveal mt-7 space-y-3.5 text-sm">
                    @foreach ([
                        'ملف منتجات (Feed) بيتحدّث لوحده مع كل تعديل في السعر أو المخزون',
                        'المنتج اللي خلص من المخزون بيتشال من الكاتالوج أوتوماتيك',
                        'إعلانات الكتالوج الديناميكية تشتغل من غير رفع يدوي',
                    ] as $point)
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <svg class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5"><path d="M20 6 9 17l-5-5"/></svg>
                            </span>
                            <span>{{ $point }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="reveal grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['Google Shopping', 'منتجاتك في نتايج تسوّق جوجل', 'M12 2a10 10 0 1 0 10 10h-10z'],
                    ['فيسبوك وإنستجرام', 'كاتالوج ميتا وإعلانات ديناميكية', 'M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z'],
                    ['تيك توك', 'كاتالوج تيك توك للتسوّق', 'M21 8a5 5 0 0 1-5-5h-4v13a3 3 0 1 1-3-3'],
                    ['متجرك', 'نفس الكتالوج اللي شغّال عندك', 'M3 9 12 2l9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'],
                ] as [$title, $body, $icon])
                    <div class="surface p-6">
                        <span class="flex size-11 items-center justify-center rounded-xl bg-muted text-foreground">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg>
                        </span>
                        <h3 class="mt-4 font-semibold">{{ $title }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ══ 7. How it works ══════════════════════════════════════════════════ --}}
<section class="py-20 md:py-28">
    <div class="mx-auto max-w-5xl px-6">
        <h2 class="reveal text-center text-balance text-3xl font-bold tracking-tight md:text-4xl">
            من التسجيل لأول طلب
        </h2>

        <div class="relative mt-14 grid gap-8 md:grid-cols-3">
            {{-- Connector, drawn behind the numbers on desktop only. --}}
            <div class="pointer-events-none absolute inset-x-12 top-6 hidden h-px bg-gradient-to-l from-transparent via-border to-transparent md:block"></div>

            @foreach ([
                ['اكتب اسم متجرك', 'دقيقة واحدة ومن غير كارت ائتمان. المتجر بيتعمل وإنت بتسجّل.'],
                ['ضيف منتجاتك', 'اسم وسعر وصورة. الأقسام والمقاسات والعروض لو محتاجها.'],
                ['شارك اللينك', 'حطه في إعلان فيسبوك أو تيك توك، والطلبات تبدأ توصلك على طول.'],
            ] as $i => [$title, $body])
                <div class="reveal relative text-center md:text-right">
                    <span class="relative z-10 mx-auto flex size-12 items-center justify-center rounded-2xl border border-gold-200/70 bg-card shadow-e1 md:mx-0">
                        <span class="text-foil tabular text-lg font-bold">٠{{ $i + 1 }}</span>
                    </span>
                    <h3 class="mt-4 text-lg font-semibold">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══ 7. Close ═════════════════════════════════════════════════════════ --}}
<section class="mesh relative overflow-hidden border-t border-border py-24 md:py-32">
    <div class="grid-lines pointer-events-none absolute inset-0"></div>

    <div class="relative mx-auto max-w-2xl px-6 text-center">
        <h2 class="reveal text-balance text-3xl font-bold leading-tight tracking-tight md:text-5xl">
            اكتب اسم متجرك، والباقي علينا
        </h2>
        <p class="reveal mt-5 text-muted-foreground">
            ٣ شهور مجانية بالكامل. من غير كارت ائتمان.
        </p>
        <a href="{{ route('register') }}" class="btn-primary sheen reveal mt-9 px-9 py-4 text-base">
            ابدأ متجرك دلوقتي
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
</script>
@endpush

@endsection

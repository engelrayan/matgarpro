<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>نظام التصميم — متجر برو</title>

    <script>
        (function () {
            var stored = localStorage.getItem('appearance');
            var dark = stored === 'dark';
            if (dark) document.documentElement.classList.add('dark');
            document.documentElement.style.backgroundColor = dark ? '#0A100E' : '#FAF9F4';
        })();
    </script>

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:300,400,500,600,700" rel="stylesheet" />
    @vite('resources/css/app.css')
</head>
<body class="font-sans">

@php
    /*
     | Every class below is written out in full on purpose. Tailwind scans this
     | file as plain text, so a class assembled at runtime ("bg-{$name}-{$step}")
     | is never seen and gets purged from the build.
     */
    $scales = [
        [
            'title' => 'اليشم — صوت العلامة',
            'usage' => 'الأزرار الأساسية، الروابط، حالات النجاح، الشريط الجانبي',
            'swatches' => [
                [50, 'bg-jade-50'], [100, 'bg-jade-100'], [200, 'bg-jade-200'],
                [300, 'bg-jade-300'], [400, 'bg-jade-400'], [500, 'bg-jade-500'],
                [600, 'bg-jade-600'], [700, 'bg-jade-700'], [800, 'bg-jade-800'],
                [900, 'bg-jade-900'], [950, 'bg-jade-950'],
            ],
        ],
        [
            'title' => 'الذهب — لهجة التأكيد',
            'usage' => 'الفلوس والأرباح والخطط المدفوعة فقط. استخدامه في كل حتة بيقتل قيمته',
            'swatches' => [
                [50, 'bg-gold-50'], [100, 'bg-gold-100'], [200, 'bg-gold-200'],
                [300, 'bg-gold-300'], [400, 'bg-gold-400'], [500, 'bg-gold-500'],
                [600, 'bg-gold-600'], [700, 'bg-gold-700'], [800, 'bg-gold-800'],
                [900, 'bg-gold-900'], [950, 'bg-gold-950'],
            ],
        ],
        [
            'title' => 'الرمل — الحياد الدافئ',
            'usage' => 'الخلفيات والحدود والنصوص الثانوية. مش رمادي ميّت — فيه دفء',
            'swatches' => [
                [50, 'bg-sand-50'], [100, 'bg-sand-100'], [200, 'bg-sand-200'],
                [300, 'bg-sand-300'], [400, 'bg-sand-400'], [500, 'bg-sand-500'],
                [600, 'bg-sand-600'], [700, 'bg-sand-700'], [800, 'bg-sand-800'],
                [900, 'bg-sand-900'], [950, 'bg-sand-950'],
            ],
        ],
    ];
@endphp

{{-- ────────────────────────────── Hero ────────────────────────────── --}}
<header class="bg-aurora relative overflow-hidden border-b border-border">
    <div class="mx-auto max-w-6xl px-6 py-20">
        <div class="animate-fade-up">
            <span class="badge-gold mb-6">نظام التصميم · الإصدار ١٫٠</span>
            <h1 class="text-5xl font-bold leading-[1.15] tracking-tight sm:text-6xl">
                <span class="text-jade-gradient">متجر برو</span>
                <span class="block text-foreground">منصة التجارة اللي التاجر يفتخر بيها</span>
            </h1>
            <p class="mt-6 max-w-2xl text-lg leading-relaxed text-muted-foreground">
                هوية «اليشم والذهب». اليشم الغامق للثقة والنمو، والذهب العتيق للفلوس والتميّز،
                وخلفية عاجية دافية بدل الأبيض الناصع — أرخص حاجة بتخلّي المنتج يبان غالي.
            </p>
            <div class="mt-9 flex flex-wrap items-center gap-3">
                <button class="btn-primary sheen px-6 py-3 text-base">ابدأ متجرك مجانًا</button>
                <button class="btn-outline px-6 py-3 text-base">شوف عرض توضيحي</button>
                <button onclick="toggleTheme()" class="btn-ghost mr-auto text-sm">
                    تبديل الوضع الليلي
                </button>
            </div>
        </div>
    </div>
</header>

<main class="mx-auto max-w-6xl space-y-20 px-6 py-20">

    {{-- ───────────────────────── Colour scales ───────────────────────── --}}
    <section>
        <div class="rule-gold mb-8">
            <h2 class="shrink-0 text-2xl font-semibold tracking-tight">الألوان</h2>
        </div>

        <div class="space-y-10">
            @foreach ($scales as $scale)
                <div>
                    <h3 class="text-base font-semibold">{{ $scale['title'] }}</h3>
                    <p class="mb-4 mt-1 text-sm text-muted-foreground">{{ $scale['usage'] }}</p>
                    <div class="grid grid-cols-6 gap-2 sm:grid-cols-11">
                        @foreach ($scale['swatches'] as [$step, $class])
                            <div class="group">
                                <div class="{{ $class }} h-16 rounded-xl shadow-e1 ring-1 ring-inset ring-black/5 transition-transform duration-200 ease-brand group-hover:scale-105"></div>
                                <div class="tabular mt-1.5 text-center text-[11px] text-muted-foreground">{{ $step }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-10 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['bg-success', '--success', 'نجاح — تسليم، تحصيل'],
                ['bg-warning', '--warning', 'تنبيه — متأخر، ناقص'],
                ['bg-destructive', '--destructive', 'خطر — رفض، إلغاء'],
                ['bg-info', '--info', 'معلومة — قيد التنفيذ'],
            ] as [$class, $token, $label])
                <div class="surface p-4">
                    <div class="{{ $class }} h-10 rounded-lg"></div>
                    <div class="mt-3 text-sm font-medium">{{ $label }}</div>
                    <code class="text-xs text-muted-foreground">{{ $token }}</code>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ───────────────────────── Typography ──────────────────────────── --}}
    <section>
        <div class="rule-gold mb-8">
            <h2 class="shrink-0 text-2xl font-semibold tracking-tight">الخط والمقاسات</h2>
        </div>
        <div class="surface divide-y divide-border">
            @foreach ([
                ['text-5xl font-bold tracking-tight', 'عنوان رئيسي', '48px / 700'],
                ['text-3xl font-semibold tracking-tight', 'عنوان قسم', '30px / 600'],
                ['text-xl font-semibold', 'عنوان بطاقة', '20px / 600'],
                ['text-base', 'نص أساسي — الخط المستخدم IBM Plex Sans Arabic، بيغطي العربي واللاتيني بنفس الخط عشان الأرقام والكلمات يقفوا على نفس الخط الأساسي.', '16px / 400'],
                ['text-sm text-muted-foreground', 'نص ثانوي وشروحات تحت الحقول', '14px / 400'],
                ['text-xs text-muted-foreground', 'تسميات صغيرة وبادچات', '12px / 400'],
            ] as [$cls, $sample, $meta])
                <div class="flex items-baseline justify-between gap-6 p-5">
                    <p class="{{ $cls }} min-w-0 flex-1">{{ $sample }}</p>
                    <code class="shrink-0 text-xs tabular text-muted-foreground">{{ $meta }}</code>
                </div>
            @endforeach
        </div>
        <div class="surface mt-4 p-5">
            <p class="mb-2 text-sm text-muted-foreground">الأرقام في الجداول بتستخدم <code class="text-xs">tabular-nums</code> عشان الأعمدة تتراص:</p>
            <div class="tabular grid max-w-xs grid-cols-2 gap-x-8 text-sm">
                <span>١٢٬٤٥٠٫٠٠</span><span class="text-left" dir="ltr">12,450.00</span>
                <span>٩٬٨٠١٫٥٠</span><span class="text-left" dir="ltr">9,801.50</span>
                <span>١١١٬١١١٫١١</span><span class="text-left" dir="ltr">111,111.11</span>
            </div>
        </div>
    </section>

    {{-- ───────────────────────── Buttons & inputs ────────────────────── --}}
    <section>
        <div class="rule-gold mb-8">
            <h2 class="shrink-0 text-2xl font-semibold tracking-tight">الأزرار والحقول</h2>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="surface p-6">
                <h3 class="mb-5 font-semibold">الأزرار</h3>
                <div class="flex flex-wrap items-center gap-3">
                    <button class="btn-primary sheen">حفظ التغييرات</button>
                    <button class="btn-gold sheen">ترقية الخطة</button>
                    <button class="btn-outline">إلغاء</button>
                    <button class="btn-ghost">تخطي</button>
                    <button class="btn-danger">حذف المتجر</button>
                    <button class="btn-primary" disabled>معطّل</button>
                </div>
                <p class="mt-5 text-sm text-muted-foreground">
                    زر ذهبي واحد بس في الشاشة — ده الإجراء اللي بيجيب فلوس. أي زر ذهبي تاني بيقلّل قيمته.
                </p>
            </div>

            <div class="surface p-6">
                <h3 class="mb-5 font-semibold">الحقول</h3>
                <div class="space-y-4">
                    <div>
                        <label class="field-label">اسم المتجر</label>
                        <input class="field" value="متجر محمود" />
                        <p class="field-hint">بيظهر للعميل في صفحة الطلب وفي رسائل التأكيد.</p>
                    </div>
                    <div>
                        <label class="field-label">الدومين المخصص</label>
                        <input class="field" dir="ltr" placeholder="mahmoud.com" />
                        <p class="field-error">الدومين ده مربوط بمتجر تاني بالفعل.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ───────────────────────── Badges & elevation ──────────────────── --}}
    <section>
        <div class="rule-gold mb-8">
            <h2 class="shrink-0 text-2xl font-semibold tracking-tight">الحالات والارتفاعات</h2>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="surface p-6">
                <h3 class="mb-5 font-semibold">بادچات الحالة</h3>
                <div class="flex flex-wrap gap-2">
                    <span class="badge-success">تم التسليم</span>
                    <span class="badge-info">في الطريق</span>
                    <span class="badge-warning">مؤجّل</span>
                    <span class="badge-danger">مرفوض</span>
                    <span class="badge-neutral">قيد التأكيد</span>
                    <span class="badge-gold">خطة مدفوعة</span>
                </div>
            </div>
            <div class="surface p-6">
                <h3 class="mb-5 font-semibold">سلّم الارتفاع</h3>
                <div class="flex flex-wrap items-center gap-4">
                    @foreach ([
                        ['shadow-e1', 'e1', 'بطاقة'],
                        ['shadow-e2', 'e2', 'لوحة'],
                        ['shadow-e3', 'e3', 'قائمة منسدلة'],
                        ['shadow-e4', 'e4', 'مودال'],
                    ] as [$class, $name, $label])
                        <div class="flex flex-col items-center gap-2">
                            <div class="{{ $class }} flex h-16 w-16 items-center justify-center rounded-xl bg-card">
                                <code class="text-xs text-muted-foreground">{{ $name }}</code>
                            </div>
                            <span class="text-xs text-muted-foreground">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ───────────────────────── Applied: dashboard ──────────────────── --}}
    <section>
        <div class="rule-gold mb-2">
            <h2 class="shrink-0 text-2xl font-semibold tracking-tight">تطبيق عملي — لوحة التاجر</h2>
        </div>
        <p class="mb-8 text-sm text-muted-foreground">نفس التوكنز، مركّبة على شاشة حقيقية.</p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="stat-tile">
                <span class="stat-tile__label">طلبات النهاردة</span>
                <span class="stat-tile__value">١٤٨</span>
                <span class="text-xs font-medium text-success">▲ ١٢٪ عن إمبارح</span>
            </div>
            <div class="stat-tile">
                <span class="stat-tile__label">نسبة التسليم</span>
                <span class="stat-tile__value">٧٨٪</span>
                <span class="text-xs font-medium text-destructive">▼ ٣٪ عن الأسبوع اللي فات</span>
            </div>
            <div class="stat-tile">
                <span class="stat-tile__label">سلات متروكة</span>
                <span class="stat-tile__value">٢٣</span>
                <span class="text-xs font-medium text-muted-foreground">٩ اتسترجعوا</span>
            </div>
            <div class="surface-lux flex flex-col gap-1 p-5">
                <span class="stat-tile__label">أرباح محصّلة</span>
                <span class="stat-tile__value text-foil">٤٨٬٢٥٠</span>
                <span class="text-xs font-medium text-muted-foreground">جنيه · بتتحوّل خلال ٢٤ ساعة</span>
            </div>
        </div>

        <div class="surface mt-4 overflow-hidden">
            <div class="flex items-center justify-between border-b border-border p-5">
                <h3 class="font-semibold">آخر الطلبات</h3>
                <button class="btn-ghost text-sm">عرض الكل</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/60 text-xs text-muted-foreground">
                        <tr>
                            <th class="p-4 text-right font-medium">رقم الطلب</th>
                            <th class="p-4 text-right font-medium">العميل</th>
                            <th class="p-4 text-right font-medium">المحافظة</th>
                            <th class="p-4 text-right font-medium">المبلغ</th>
                            <th class="p-4 text-right font-medium">الحالة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ([
                            ['MT-10482', 'أحمد سيد', 'القاهرة', '٦٥٠٫٠٠', 'badge-success', 'تم التسليم'],
                            ['MT-10481', 'منى عبد الله', 'الجيزة', '١٬٢٠٠٫٠٠', 'badge-info', 'في الطريق'],
                            ['MT-10480', 'كريم فؤاد', 'الإسكندرية', '٤٢٠٫٠٠', 'badge-warning', 'مؤجّل'],
                            ['MT-10479', 'سارة محمود', 'المنصورة', '٨٩٠٫٠٠', 'badge-danger', 'مرفوض'],
                        ] as [$no, $customer, $gov, $amount, $badge, $label])
                            <tr class="transition-colors hover:bg-muted/40">
                                <td class="p-4 font-medium" dir="ltr" align="right">{{ $no }}</td>
                                <td class="p-4">{{ $customer }}</td>
                                <td class="p-4 text-muted-foreground">{{ $gov }}</td>
                                <td class="p-4 font-medium">{{ $amount }}</td>
                                <td class="p-4"><span class="{{ $badge }}">{{ $label }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ───────────────────────── Applied: storefront ─────────────────── --}}
    <section>
        <div class="rule-gold mb-2">
            <h2 class="shrink-0 text-2xl font-semibold tracking-tight">تطبيق عملي — صفحة المنتج</h2>
        </div>
        <p class="mb-8 text-sm text-muted-foreground">اللي العميل بيشوفه على دومين التاجر.</p>

        <div class="surface overflow-hidden">
            <div class="grid gap-8 p-8 lg:grid-cols-2">
                <div class="aspect-square rounded-2xl bg-gradient-to-br from-jade-100 to-gold-100 ring-1 ring-inset ring-black/5 dark:from-jade-900 dark:to-jade-800"></div>
                <div class="flex flex-col justify-center">
                    <span class="badge-danger mb-4 w-fit">آخر ٧ قطع</span>
                    <h3 class="text-3xl font-bold tracking-tight">ساعة يد كلاسيك</h3>
                    <div class="mt-4 flex items-baseline gap-3">
                        <span class="text-3xl font-bold text-primary">٧٩٩ ج.م</span>
                        <span class="text-lg text-muted-foreground line-through">١٬٢٠٠ ج.م</span>
                        <span class="badge-success">وفّر ٣٣٪</span>
                    </div>

                    <div class="surface-lux mt-6 p-4">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium">اشترِ ٢ واحصل على ١ مجانًا</span>
                            <span class="badge-gold">أوفر</span>
                        </div>
                    </div>

                    <div class="mt-6 space-y-3">
                        <input class="field" placeholder="الاسم بالكامل" />
                        <input class="field" dir="ltr" placeholder="01xxxxxxxxx" />
                        <select class="field">
                            <option>اختر المحافظة</option>
                            <option>القاهرة — الشحن ٥٠ ج.م</option>
                            <option>الإسكندرية — الشحن ٦٥ ج.م</option>
                        </select>
                    </div>

                    <button class="btn-primary sheen mt-5 w-full py-4 text-base">
                        اطلب الآن — الدفع عند الاستلام
                    </button>
                    <p class="mt-3 text-center text-xs text-muted-foreground">
                        شحن لكل المحافظات · استلم وافحص قبل الدفع
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ───────────────────────── Rules ───────────────────────────────── --}}
    <section>
        <div class="rule-gold mb-8">
            <h2 class="shrink-0 text-2xl font-semibold tracking-tight">قواعد الاستخدام</h2>
        </div>
        <div class="surface-ink p-8">
            <ol class="space-y-4 text-sm leading-relaxed">
                @foreach ([
                    'الذهب للفلوس بس — الأرباح، الخطط، الترقية. أي استخدام تاني بيحرقه.',
                    'زر أساسي واحد لكل شاشة. لو فيه اتنين، يبقى واحد منهم مش أساسي.',
                    'ممنوع أي هيكس في الكومبوننت. أي لون جديد بيتضاف كتوكن في app.css الأول.',
                    'الأخضر = تسليم/تحصيل بس. مينفعش يستخدم لـ«تم الحفظ» — بيلغبط التاجر.',
                    'كل رقم فلوس بـ tabular-nums، وكل جدول أعمدته لازم تتراص.',
                    'أي حركة بتحترم prefers-reduced-motion، والـ easing واحد في المنتج كله.',
                ] as $i => $rule)
                    <li class="flex gap-4">
                        <span class="tabular flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-gold-500/15 text-xs font-semibold text-gold-300">{{ $i + 1 }}</span>
                        <span class="text-jade-100/90">{{ $rule }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
</main>

<footer class="border-t border-border py-10 text-center text-sm text-muted-foreground">
    متجر برو · نظام التصميم — هذه الصفحة هي المرجع، والكود هو مصدر الحقيقة.
</footer>

<script>
    function toggleTheme() {
        var root = document.documentElement;
        var dark = root.classList.toggle('dark');
        localStorage.setItem('appearance', dark ? 'dark' : 'light');
        root.style.backgroundColor = dark ? '#0A100E' : '#FAF9F4';
    }
</script>
</body>
</html>

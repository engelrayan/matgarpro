<?php

namespace App\Services\Builder;

/**
 * Every part a merchant can put on a page, and every setting it takes.
 *
 * This is the single source of truth for four different things that would
 * otherwise drift apart within a month:
 *
 *  1. the catalogue the builder shows in "أضف جزء",
 *  2. the form controls rendered in the settings panel,
 *  3. the validation rules applied on save,
 *  4. the defaults a brand-new part starts with.
 *
 * All four are derived from the `fields` array below, so adding a setting is
 * one edit here plus one line in the part's blade — never a change in four
 * places where the fourth is the one that gets forgotten and lets unvalidated
 * merchant input through.
 *
 * ── On the wording ──────────────────────────────────────────────────────
 * Every `name`, `label`, `hint` and option in this file is read by a shop
 * owner, not a developer. Two rules hold:
 *
 *  1. **No jargon.** "الهيرو", "شريط الثقة", "شبكة المنتجات" and "الشرايح"
 *     mean nothing to somebody who sells clothes. They are named after what
 *     the merchant sees on their own page.
 *  2. **"قسم" only ever means a product category.** A block of the page is a
 *     "جزء". The two used to share a word, and a merchant reading "أضف قسم"
 *     could not tell whether they were about to add a page block or a
 *     category of products.
 *
 * Field types the builder knows how to render:
 *   text · textarea · richtext · number · toggle · select · color · image
 *   link · products · categories · datetime · repeater
 */
class SectionRegistry
{
    /** Pages that carry a part list. */
    public const PAGES = ['home', 'product', 'category', 'header', 'footer'];

    /**
     * A product's own landing page — one per product, keyed `landing-{id}`.
     *
     * Not in PAGES: there is one per product rather than one per store, so it
     * is a *kind* of page. Everything that asks "may this part live here?"
     * asks it of the kind, through kindOf().
     */
    public const LANDING = 'landing';

    public static function landingKey(int $productId): string
    {
        return self::LANDING . '-' . $productId;
    }

    /** The product a landing key belongs to, or null for any other key. */
    public static function landingProductId(string $page): ?int
    {
        return preg_match('/^landing-([1-9]\d{0,11})$/', $page, $m) ? (int) $m[1] : null;
    }

    /** What sort of page a key is: one of PAGES, LANDING, or null for nonsense. */
    public static function kindOf(string $page): ?string
    {
        if (in_array($page, self::PAGES, true)) {
            return $page;
        }

        return self::landingProductId($page) !== null ? self::LANDING : null;
    }

    public static function isPage(string $page): bool
    {
        return self::kindOf($page) !== null;
    }

    public static function pageLabel(string $page): string
    {
        return [
            'home' => 'الصفحة الرئيسية',
            'product' => 'صفحة المنتج',
            'category' => 'صفحة القسم',
            'header' => 'أعلى الصفحة',
            'footer' => 'أسفل الصفحة',
            self::LANDING => 'صفحة الهبوط',
        ][self::kindOf($page) ?? $page] ?? $page;
    }

    /** Shared by every "كام في الصف؟" control, so they cannot drift apart. */
    private function columnsField(string $default = '4'): array
    {
        return ['key' => 'columns', 'type' => 'select', 'label' => 'كام واحد في الصف؟', 'default' => $default, 'options' => [
            ['value' => '2', 'label' => '٢ في الصف'],
            ['value' => '3', 'label' => '٣ في الصف'],
            ['value' => '4', 'label' => '٤ في الصف'],
        ]];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function all(): array
    {
        return [
            // ── Home ──────────────────────────────────────────────────────
            'hero' => [
                'name' => 'الصورة الكبيرة فوق',
                'description' => 'أول حاجة الزبون بيشوفها أول ما يفتح متجرك.',
                'icon' => 'Presentation',
                'pages' => ['home'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'source', 'type' => 'select', 'label' => 'الصور تيجي منين؟', 'default' => 'auto',
                        'options' => [
                            ['value' => 'auto', 'label' => 'من منتجاتك تلقائي'],
                            ['value' => 'manual', 'label' => 'صور أرفعها بنفسي'],
                        ],
                        'hint' => 'لو سيبتها «من منتجاتك» مش هتحتاج ترفع أي صورة — بتتعمل لوحدها، وبتبدأ باللي عليه خصم.'],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'كام منتج يتعرض؟', 'default' => 3, 'min' => 1, 'max' => 8,
                        'when' => ['source' => 'auto']],
                    ['key' => 'slides', 'type' => 'repeater', 'label' => 'الصور', 'default' => [], 'max' => 8,
                        'when' => ['source' => 'manual'],
                        'item_label' => 'title',
                        'fields' => [
                            ['key' => 'image', 'type' => 'image', 'label' => 'الصورة', 'default' => null],
                            ['key' => 'title', 'type' => 'text', 'label' => 'الكلام الكبير', 'default' => '', 'max' => 120],
                            ['key' => 'subtitle', 'type' => 'text', 'label' => 'سطر صغير تحته', 'default' => '', 'max' => 200],
                            ['key' => 'button_text', 'type' => 'text', 'label' => 'الكلام المكتوب على الزرار', 'default' => 'اطلب دلوقتي', 'max' => 40],
                            ['key' => 'link', 'type' => 'link', 'label' => 'الزرار يودّي فين؟', 'default' => ''],
                        ]],
                    ['key' => 'autoplay', 'type' => 'toggle', 'label' => 'الصور تتبدّل لوحدها', 'default' => true],
                    /*
                     | Four seconds, not six. At six a visitor who lands, reads
                     | the headline and starts scrolling never sees slide two —
                     | so the merchant assumes the slider is broken, which is
                     | exactly the report that produced this change.
                     */
                    ['key' => 'interval', 'type' => 'number', 'label' => 'تتبدّل كل كام ثانية؟', 'default' => 4, 'min' => 2, 'max' => 30,
                        'when' => ['autoplay' => true]],
                ],
            ],

            'trust_bar' => [
                'name' => 'مميزات متجرك',
                'description' => 'سطور قصيرة بتطمّن الزبون: الدفع عند الاستلام، الشحن، الاستبدال.',
                'icon' => 'ShieldCheck',
                'pages' => ['home', 'product', 'category', 'landing'],
                'limit' => 2,
                'fields' => [
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'المميزات', 'default' => [
                        ['icon' => 'check', 'title' => 'الدفع عند الاستلام', 'body' => 'ادفع لما يوصلك'],
                        ['icon' => 'truck', 'title' => 'شحن لكل المحافظات', 'body' => 'من ٢ لـ ٥ أيام'],
                        ['icon' => 'refresh', 'title' => 'استبدال ١٤ يوم', 'body' => 'من غير أسئلة'],
                    ], 'max' => 4, 'item_label' => 'title', 'fields' => [
                        ['key' => 'icon', 'type' => 'select', 'label' => 'الرسمة', 'default' => 'check', 'options' => [
                            ['value' => 'check', 'label' => 'علامة صح'],
                            ['value' => 'truck', 'label' => 'عربية شحن'],
                            ['value' => 'refresh', 'label' => 'سهم استبدال'],
                            ['value' => 'shield', 'label' => 'درع ضمان'],
                            ['value' => 'phone', 'label' => 'تليفون'],
                            ['value' => 'wallet', 'label' => 'محفظة فلوس'],
                        ]],
                        ['key' => 'title', 'type' => 'text', 'label' => 'الكلام', 'default' => '', 'max' => 60],
                        ['key' => 'body', 'type' => 'text', 'label' => 'شرح بسيط تحته', 'default' => '', 'max' => 80],
                    ]],
                ],
            ],

            'deals' => [
                'name' => 'العروض والخصومات',
                'description' => 'المنتجات اللي عليها خصم شغّال، والأقرب في انتهاء العرض بيظهر الأول.',
                'icon' => 'Tag',
                'pages' => ['home'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'الكلام اللي فوق', 'default' => 'عروض النهارده', 'max' => 80],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'كام منتج يتعرض؟', 'default' => 8, 'min' => 2, 'max' => 20],
                ],
            ],

            'categories' => [
                'name' => 'أقسام المتجر',
                'description' => 'كروت أقسام منتجاتك. القسم الفاضي بيتخفي لوحده.',
                'icon' => 'LayoutGrid',
                'pages' => ['home'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'الكلام اللي فوق', 'default' => 'تسوّق حسب القسم', 'max' => 80],
                    $this->columnsField(),
                    ['key' => 'limit', 'type' => 'number', 'label' => 'أكتر كام قسم يظهر؟', 'default' => 8, 'min' => 2, 'max' => 24],
                ],
            ],

            'featured_products' => [
                'name' => 'منتجات تختارها بنفسك',
                'description' => 'أنت اللي بتحدد المنتجات وترتيبها — مش تلقائي.',
                'icon' => 'Star',
                'pages' => ['home', 'product', 'category', 'landing'],
                'limit' => 3,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'الكلام اللي فوق', 'default' => 'الأكثر مبيعاً', 'max' => 80],
                    ['key' => 'products', 'type' => 'products', 'label' => 'المنتجات', 'default' => [], 'max' => 12],
                    $this->columnsField(),
                ],
            ],

            'product_grid' => [
                'name' => 'كل المنتجات',
                'description' => 'عرض منتجاتك — كلها أو الأحدث أو قسم معيّن.',
                'icon' => 'Grid3x3',
                'pages' => ['home'],
                'limit' => 2,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'الكلام اللي فوق', 'default' => 'كل المنتجات', 'max' => 80],
                    ['key' => 'source', 'type' => 'select', 'label' => 'يعرض إيه؟', 'default' => 'all', 'options' => [
                        ['value' => 'all', 'label' => 'كل منتجاتي'],
                        ['value' => 'newest', 'label' => 'أحدث المنتجات'],
                        ['value' => 'discounted', 'label' => 'اللي عليه خصم بس'],
                        ['value' => 'category', 'label' => 'قسم واحد أختاره'],
                    ]],
                    ['key' => 'category', 'type' => 'categories', 'label' => 'أنهي قسم؟', 'default' => [], 'max' => 1,
                        'when' => ['source' => 'category']],
                    $this->columnsField(),
                    ['key' => 'limit', 'type' => 'number', 'label' => 'كام منتج في الصفحة؟', 'default' => 24, 'min' => 4, 'max' => 48],
                    ['key' => 'paginate', 'type' => 'toggle', 'label' => 'باقي المنتجات في صفحات تانية', 'default' => true,
                        'hint' => 'اقفلها لو عايز عدد ثابت يظهر وخلاص من غير صفحة ٢ و٣.'],
                ],
            ],

            'banner' => [
                'name' => 'صورة إعلانية',
                'description' => 'صورة عريضة أو صورتين جنب بعض، كل واحدة بتودّي لمكان.',
                'icon' => 'Image',
                'pages' => ['home', 'product', 'category', 'landing'],
                'limit' => 4,
                'fields' => [
                    ['key' => 'layout', 'type' => 'select', 'label' => 'صورة واحدة ولا اتنين؟', 'default' => 'full', 'options' => [
                        ['value' => 'full', 'label' => 'صورة واحدة عريضة'],
                        ['value' => 'split', 'label' => 'صورتين جنب بعض'],
                    ]],
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'الصور', 'default' => [], 'max' => 2,
                        'item_label' => 'headline', 'fields' => [
                            ['key' => 'image', 'type' => 'image', 'label' => 'الصورة', 'default' => null],
                            ['key' => 'headline', 'type' => 'text', 'label' => 'الكلام الكبير على الصورة', 'default' => '', 'max' => 100],
                            ['key' => 'sub', 'type' => 'text', 'label' => 'سطر صغير تحته', 'default' => '', 'max' => 160],
                            ['key' => 'button_text', 'type' => 'text', 'label' => 'الكلام المكتوب على الزرار', 'default' => '', 'max' => 40],
                            ['key' => 'link', 'type' => 'link', 'label' => 'الصورة تودّي فين؟', 'default' => ''],
                        ]],
                ],
            ],

            'rich_text' => [
                'name' => 'كلام ومعلومات',
                'description' => 'قصة المتجر، سياسة الاستبدال، أي كلام عايز تقوله للزبون.',
                'icon' => 'Type',
                'pages' => ['home', 'product', 'category', 'footer', 'landing'],
                'limit' => 4,
                'fields' => [
                    ['key' => 'heading', 'type' => 'text', 'label' => 'الكلام اللي فوق', 'default' => '', 'max' => 100],
                    ['key' => 'body', 'type' => 'richtext', 'label' => 'الكلام', 'default' => ''],
                    ['key' => 'align', 'type' => 'select', 'label' => 'الكلام يبدأ منين؟', 'default' => 'right', 'options' => [
                        ['value' => 'right', 'label' => 'من اليمين'],
                        ['value' => 'center', 'label' => 'في النص'],
                    ]],
                    ['key' => 'width', 'type' => 'select', 'label' => 'عرض الكلام', 'default' => 'narrow', 'options' => [
                        ['value' => 'narrow', 'label' => 'ضيّق — أسهل في القراية'],
                        ['value' => 'wide', 'label' => 'عريض على الشاشة كلها'],
                    ]],
                ],
            ],

            'faq' => [
                'name' => 'أسئلة وأجوبة',
                'description' => 'أسئلة الزبون بتتفتح بالضغط. بتقلل مكالمات التأكيد.',
                'icon' => 'MessageCircleQuestion',
                'pages' => ['home', 'product', 'category', 'landing'],
                'limit' => 2,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'الكلام اللي فوق', 'default' => 'أسئلة شائعة', 'max' => 80],
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'الأسئلة', 'default' => [], 'max' => 15,
                        'item_label' => 'question', 'fields' => [
                            ['key' => 'question', 'type' => 'text', 'label' => 'السؤال', 'default' => '', 'max' => 200],
                            ['key' => 'answer', 'type' => 'textarea', 'label' => 'الإجابة', 'default' => '', 'max' => 1000],
                        ]],
                ],
            ],

            'video' => [
                'name' => 'فيديو',
                'description' => 'فيديو من يوتيوب. مش بيتحمّل غير لما الزبون يدوس عليه.',
                'icon' => 'Youtube',
                'pages' => ['home', 'product', 'category', 'landing'],
                'limit' => 2,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'الكلام اللي فوق', 'default' => '', 'max' => 100],
                    ['key' => 'url', 'type' => 'text', 'label' => 'لينك الفيديو', 'default' => '', 'max' => 300,
                        'hint' => 'افتح الفيديو على يوتيوب وانسخ اللينك من فوق والصقه هنا.'],
                    ['key' => 'caption', 'type' => 'text', 'label' => 'سطر صغير تحت الفيديو', 'default' => '', 'max' => 200],
                ],
            ],

            'testimonials' => [
                'name' => 'آراء العملاء',
                'description' => 'رأي زبون باسمه وتقييمه. أقوى بكتير من أي كلام عن نفسك.',
                'icon' => 'Quote',
                'pages' => ['home', 'product', 'category', 'landing'],
                'limit' => 2,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'الكلام اللي فوق', 'default' => 'عملاؤنا بيقولوا', 'max' => 80],
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'الآراء', 'default' => [], 'max' => 12,
                        'item_label' => 'name', 'fields' => [
                            ['key' => 'name', 'type' => 'text', 'label' => 'اسم الزبون', 'default' => '', 'max' => 80],
                            ['key' => 'city', 'type' => 'text', 'label' => 'المدينة', 'default' => '', 'max' => 60],
                            ['key' => 'rating', 'type' => 'number', 'label' => 'كام نجمة من ٥؟', 'default' => 5, 'min' => 1, 'max' => 5],
                            ['key' => 'text', 'type' => 'textarea', 'label' => 'كلام الزبون', 'default' => '', 'max' => 500],
                            ['key' => 'image', 'type' => 'image', 'label' => 'صورته (لو موجودة)', 'default' => null],
                        ]],
                ],
            ],

            'countdown' => [
                'name' => 'عدّاد الوقت الفاضل للعرض',
                'description' => 'ساعة بتعدّ لحد ما العرض يخلص، وبتختفي لوحدها بعد كده.',
                'icon' => 'Timer',
                'pages' => ['home', 'product', 'category', 'landing'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'الكلام اللي فوق', 'default' => 'العرض ينتهي خلال', 'max' => 100],
                    ['key' => 'subtitle', 'type' => 'text', 'label' => 'سطر صغير تحته', 'default' => '', 'max' => 160],
                    ['key' => 'ends_at', 'type' => 'datetime', 'label' => 'العرض بيخلص إمتى؟', 'default' => null],
                    ['key' => 'button_text', 'type' => 'text', 'label' => 'الكلام المكتوب على الزرار', 'default' => 'اطلب قبل ما يخلص', 'max' => 40],
                    ['key' => 'link', 'type' => 'link', 'label' => 'الزرار يودّي فين؟', 'default' => ''],
                    ['key' => 'style', 'type' => 'select', 'label' => 'شكل الخلفية', 'default' => 'solid', 'options' => [
                        ['value' => 'solid', 'label' => 'ملوّنة بلون متجرك'],
                        ['value' => 'soft', 'label' => 'فاتحة وهادية'],
                    ]],
                ],
            ],

            'brands' => [
                'name' => 'لوجوهات ماركات',
                'description' => 'صف لوجوهات — ماركات بتبيعها أو جهات بتتعامل معاها.',
                'icon' => 'Sparkles',
                'pages' => ['home', 'product', 'landing'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'الكلام اللي فوق', 'default' => '', 'max' => 80],
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'اللوجوهات', 'default' => [], 'max' => 12,
                        'item_label' => 'name', 'fields' => [
                            ['key' => 'image', 'type' => 'image', 'label' => 'صورة اللوجو', 'default' => null],
                            ['key' => 'name', 'type' => 'text', 'label' => 'اسم الماركة', 'default' => '', 'max' => 60],
                            ['key' => 'link', 'type' => 'link', 'label' => 'لينك (لو عايز)', 'default' => ''],
                        ]],
                    ['key' => 'grayscale', 'type' => 'toggle', 'label' => 'اعرضهم أبيض وأسود', 'default' => true,
                        'hint' => 'بيخلي لوجوهات غيرك ما تسرقش النظر من منتجاتك.'],
                ],
            ],

            // ── Product page ──────────────────────────────────────────────
            'product_main' => [
                'name' => 'صور المنتج وفورم الطلب',
                'description' => 'الصور والسعر والفورم اللي الزبون بيطلب منه. ده الجزء الأساسي ومايتشالش.',
                'icon' => 'ShoppingCart',
                'pages' => ['product', 'landing'],
                'limit' => 1,
                'locked' => true,
                /*
                 | Deliberately thin. The things a merchant most wants to change
                 | on a product page — the buy button's wording, whether the form
                 | comes before the description, the sticky bar — are already
                 | per-product settings on the product itself, and they have to
                 | stay that way: those are decisions that differ between a
                 | ٦٩-جنيه impulse buy and a ٣٠٠٠-جنيه considered one. Copying
                 | them here would give the merchant two switches for one
                 | behaviour and no way to tell which one won.
                 */
                'fields' => [
                    ['key' => 'badge', 'type' => 'text', 'label' => 'كلمة صغيرة فوق اسم المنتج', 'default' => '', 'max' => 40,
                        'hint' => 'زي «الأكثر مبيعاً». سيبها فاضية وهتختفي.'],
                    ['key' => 'gallery', 'type' => 'toggle', 'label' => 'اعرض صور المنتج جنب الفورم', 'default' => true,
                        'hint' => 'في صفحة الهبوط العنوان الكبير بيعرض الصورة، فالأحسن تقفلها هنا.'],
                ],
            ],

            'product_description' => [
                'name' => 'تفاصيل المنتج',
                'description' => 'الوصف اللي كاتبه في صفحة المنتج نفسه.',
                'icon' => 'AlignRight',
                'pages' => ['product', 'landing'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'heading', 'type' => 'text', 'label' => 'الكلام اللي فوق', 'default' => 'تفاصيل المنتج', 'max' => 80],
                ],
            ],

            'related_products' => [
                'name' => 'منتجات تانية ممكن تعجبه',
                'description' => 'منتجات من نفس القسم، عشان الزبون ما يخرجش من غير ما يشوف حاجة تانية.',
                'icon' => 'Shuffle',
                'pages' => ['product', 'landing'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'الكلام اللي فوق', 'default' => 'ممكن يعجبك كمان', 'max' => 80],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'كام منتج يتعرض؟', 'default' => 4, 'min' => 2, 'max' => 12],
                ],
            ],

            // ── Landing page ──────────────────────────────────────────────
            /*
             | A landing page sells one product to somebody who has never heard
             | of the shop, usually in the eight seconds after an ad. These are
             | the parts that do that: a promise, the reasons, the comparison,
             | the guarantee, and proof that real people are buying. Every
             | button on them scrolls to the order form rather than leaving
             | the page — the form is the only exit that pays.
             */
            'landing_hero' => [
                'name' => 'العنوان الكبير',
                'description' => 'أول حاجة الزبون يشوفها: وعد المنتج في جملة، صورة، وزرار يودّيه للطلب.',
                'icon' => 'Rocket',
                'pages' => ['landing'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'badge', 'type' => 'text', 'label' => 'كلمة صغيرة فوق العنوان', 'default' => '', 'max' => 40,
                        'hint' => 'زي «عرض لفترة محدودة». سيبها فاضية وهتختفي.'],
                    ['key' => 'headline', 'type' => 'text', 'label' => 'العنوان', 'default' => '', 'max' => 120,
                        'hint' => 'الفايدة مش اسم المنتج: «شعر أقل، من غير وجع» أقوى من «جهاز إزالة الشعر».'],
                    ['key' => 'subheadline', 'type' => 'textarea', 'label' => 'الكلام اللي تحته', 'default' => '', 'max' => 300],
                    ['key' => 'image', 'type' => 'image', 'label' => 'الصورة',
                        'hint' => 'سيبها فاضية وهنحط أول صورة للمنتج.'],
                    ['key' => 'points', 'type' => 'repeater', 'label' => 'نقط سريعة تحت العنوان', 'default' => [], 'max' => 4,
                        'item_label' => 'text', 'fields' => [
                            ['key' => 'text', 'type' => 'text', 'label' => 'النقطة', 'default' => '', 'max' => 60],
                        ]],
                    ['key' => 'show_price', 'type' => 'toggle', 'label' => 'اعرض السعر', 'default' => true],
                    ['key' => 'button', 'type' => 'text', 'label' => 'كلام الزرار', 'default' => 'اطلب دلوقتي', 'max' => 40],
                    ['key' => 'style', 'type' => 'select', 'label' => 'الشكل', 'default' => 'soft', 'options' => [
                        ['value' => 'soft', 'label' => 'هادي بلون المتجر'],
                        ['value' => 'bold', 'label' => 'لون المتجر كامل'],
                        ['value' => 'plain', 'label' => 'أبيض'],
                    ]],
                ],
            ],

            'benefits' => [
                'name' => 'مميزات المنتج',
                'description' => 'كروت فيها ليه المنتج ده يستاهل: صورة أو رسمة، عنوان، وسطر.',
                'icon' => 'Sparkles',
                'pages' => ['landing', 'product'],
                'limit' => 3,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => 'ليه هتحبه؟', 'max' => 100],
                    ['key' => 'subtitle', 'type' => 'textarea', 'label' => 'سطر تحت العنوان', 'default' => '', 'max' => 240],
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'المميزات', 'default' => [], 'max' => 8,
                        'item_label' => 'title', 'fields' => [
                            ['key' => 'image', 'type' => 'image', 'label' => 'صورة (اختياري)'],
                            ['key' => 'icon', 'type' => 'select', 'label' => 'أو رسمة', 'default' => 'sparkle', 'options' => [
                                ['value' => 'sparkle', 'label' => 'لمعة'],
                                ['value' => 'check', 'label' => 'صح'],
                                ['value' => 'bolt', 'label' => 'سرعة'],
                                ['value' => 'heart', 'label' => 'قلب'],
                                ['value' => 'shield', 'label' => 'حماية'],
                                ['value' => 'leaf', 'label' => 'طبيعي'],
                                ['value' => 'clock', 'label' => 'وقت'],
                                ['value' => 'gift', 'label' => 'هدية'],
                            ]],
                            ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => '', 'max' => 60],
                            ['key' => 'body', 'type' => 'textarea', 'label' => 'الشرح', 'default' => '', 'max' => 220],
                        ]],
                    $this->columnsField('2'),
                    ['key' => 'button', 'type' => 'text', 'label' => 'زرار تحت الكروت', 'default' => '', 'max' => 40,
                        'hint' => 'سيبه فاضي ومش هيظهر.'],
                ],
            ],

            'comparison' => [
                'name' => 'مقارنة',
                'description' => 'جدول صح وغلط: منتجك قدام البدائل اللي الزبون بيفكر فيها.',
                'icon' => 'Scale',
                'pages' => ['landing', 'product'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => 'الفرق واضح', 'max' => 100],
                    ['key' => 'us_label', 'type' => 'text', 'label' => 'اسم عمودك', 'default' => '', 'max' => 30,
                        'hint' => 'سيبه فاضي وهيكتب اسم المنتج.'],
                    ['key' => 'them_1', 'type' => 'text', 'label' => 'البديل الأول', 'default' => 'البدائل التانية', 'max' => 30],
                    ['key' => 'them_2', 'type' => 'text', 'label' => 'البديل التاني', 'default' => '', 'max' => 30,
                        'hint' => 'سيبه فاضي لو مقارنة بحاجة واحدة بس.'],
                    ['key' => 'rows', 'type' => 'repeater', 'label' => 'المقارنات', 'default' => [], 'max' => 8,
                        'item_label' => 'feature', 'fields' => [
                            ['key' => 'feature', 'type' => 'text', 'label' => 'الميزة', 'default' => '', 'max' => 80],
                            ['key' => 'us', 'type' => 'toggle', 'label' => 'منتجك فيه كده', 'default' => true],
                            ['key' => 'them_1', 'type' => 'toggle', 'label' => 'البديل الأول فيه كده', 'default' => false],
                            ['key' => 'them_2', 'type' => 'toggle', 'label' => 'البديل التاني فيه كده', 'default' => false],
                        ]],
                ],
            ],

            'guarantee' => [
                'name' => 'الضمان',
                'description' => 'الوعد اللي بيشيل الخوف من أول طلب: استرجاع، استبدال، أو ضمان.',
                'icon' => 'BadgeCheck',
                'pages' => ['landing', 'product', 'home'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'badge', 'type' => 'text', 'label' => 'الكلمة اللي في الختم', 'default' => '', 'max' => 16,
                        'hint' => 'زي «١٤ يوم». اكتب بس اللي فعلاً بتعمله.'],
                    ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => '', 'max' => 80],
                    ['key' => 'body', 'type' => 'textarea', 'label' => 'التفاصيل', 'default' => '', 'max' => 400],
                    ['key' => 'button', 'type' => 'text', 'label' => 'كلام الزرار', 'default' => '', 'max' => 40,
                        'hint' => 'سيبه فاضي ومش هيظهر.'],
                ],
            ],

            'image_stack' => [
                'name' => 'صور ورا بعض',
                'description' => 'صور مصمّمة بعرض الصفحة لازقة في بعض — زي البوستات اللي في الإعلان.',
                'icon' => 'Images',
                'pages' => ['landing', 'product', 'home'],
                'limit' => 4,
                'fields' => [
                    ['key' => 'images', 'type' => 'repeater', 'label' => 'الصور', 'default' => [], 'max' => 12,
                        'item_label' => 'alt', 'fields' => [
                            ['key' => 'image', 'type' => 'image', 'label' => 'الصورة'],
                            ['key' => 'alt', 'type' => 'text', 'label' => 'وصف قصير للصورة', 'default' => '', 'max' => 120,
                                'hint' => 'مش هيظهر — بيساعد جوجل والناس اللي بتقرا بالصوت.'],
                        ]],
                    ['key' => 'width', 'type' => 'select', 'label' => 'العرض', 'default' => 'narrow', 'options' => [
                        ['value' => 'narrow', 'label' => 'عرض الموبايل في النص'],
                        ['value' => 'full', 'label' => 'عرض الصفحة كله'],
                    ]],
                ],
            ],

            /*
             | Social proof from the store's own orders, never invented. The
             | section asks nothing of the merchant but whether to show it, and
             | renders nothing at all until there is something true to say.
             */
            'live_orders' => [
                'name' => 'الناس بتطلب دلوقتي',
                'description' => 'من طلباتك الحقيقية: كام واحد طلب المنتج ده، ومنين — من غير أسماء ولا أرقام.',
                'icon' => 'Activity',
                'pages' => ['landing', 'product'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'show_count', 'type' => 'toggle', 'label' => 'اعرض عدد الطلبات آخر ٧ أيام', 'default' => true],
                    ['key' => 'show_recent', 'type' => 'toggle', 'label' => 'اعرض آخر الطلبات ومنين', 'default' => true],
                    ['key' => 'toasts', 'type' => 'toggle', 'label' => 'إشعار صغير بيظهر تحت لما حد يطلب', 'default' => true],
                ],
            ],

            // ── Category page ─────────────────────────────────────────────
            'category_header' => [
                'name' => 'اسم القسم',
                'description' => 'اسم القسم ووصفه وعدد منتجاته. ده الجزء الأساسي ومايتشالش.',
                'icon' => 'Heading',
                'pages' => ['category'],
                'limit' => 1,
                'locked' => true,
                'fields' => [
                    ['key' => 'show_description', 'type' => 'toggle', 'label' => 'اعرض وصف القسم', 'default' => true],
                    ['key' => 'show_count', 'type' => 'toggle', 'label' => 'اعرض عدد المنتجات', 'default' => true],
                ],
            ],

            'category_products' => [
                'name' => 'منتجات القسم',
                'description' => 'منتجات القسم اللي الزبون فاتحه. ده الجزء الأساسي ومايتشالش.',
                'icon' => 'Grid3x3',
                'pages' => ['category'],
                'limit' => 1,
                'locked' => true,
                'fields' => [
                    $this->columnsField(),
                ],
            ],

            // ── Header ────────────────────────────────────────────────────
            'announcement_bar' => [
                'name' => 'الشريط الملوّن فوق خالص',
                'description' => 'سطر واحد فوق اللوجو — مكان كويس لأهم عرض عندك.',
                'icon' => 'Megaphone',
                'pages' => ['header'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'text', 'type' => 'text', 'label' => 'الكلام', 'max' => 160,
                        'default' => 'الدفع عند الاستلام · شحن لكل المحافظات · استبدال خلال ١٤ يوم'],
                    ['key' => 'link', 'type' => 'link', 'label' => 'يودّي فين لو داس عليه؟', 'default' => ''],
                    ['key' => 'style', 'type' => 'select', 'label' => 'لون الشريط', 'default' => 'primary', 'options' => [
                        ['value' => 'primary', 'label' => 'لون متجرك الأساسي'],
                        ['value' => 'dark', 'label' => 'غامق'],
                        ['value' => 'accent', 'label' => 'لون متجرك التاني'],
                    ]],
                ],
            ],

            'header_nav' => [
                'name' => 'اللوجو والقوايم',
                'description' => 'الشريط اللي فيه لوجو متجرك وروابطه والبحث. ده الجزء الأساسي ومايتشالش.',
                'icon' => 'PanelTop',
                'pages' => ['header'],
                'limit' => 1,
                'locked' => true,
                'fields' => [
                    ['key' => 'sticky', 'type' => 'toggle', 'label' => 'يفضل ظاهر وانت بتنزل في الصفحة', 'default' => true],
                    ['key' => 'show_search', 'type' => 'toggle', 'label' => 'اعرض خانة البحث', 'default' => true],
                    ['key' => 'links_source', 'type' => 'select', 'label' => 'الروابط اللي جنب اللوجو', 'default' => 'categories', 'options' => [
                        ['value' => 'categories', 'label' => 'أقسام منتجاتي تلقائي'],
                        ['value' => 'manual', 'label' => 'روابط أكتبها بنفسي'],
                        ['value' => 'none', 'label' => 'من غير روابط'],
                    ]],
                    ['key' => 'links', 'type' => 'repeater', 'label' => 'الروابط', 'default' => [], 'max' => 6,
                        'when' => ['links_source' => 'manual'], 'item_label' => 'label', 'fields' => [
                            ['key' => 'label', 'type' => 'text', 'label' => 'الكلام الظاهر', 'default' => '', 'max' => 40],
                            ['key' => 'url', 'type' => 'link', 'label' => 'يودّي فين؟', 'default' => ''],
                        ]],
                ],
            ],

            // ── Footer ────────────────────────────────────────────────────
            'footer_main' => [
                'name' => 'روابط ومعلومات آخر الصفحة',
                'description' => 'الأعمدة اللي تحت خالص. ده الجزء الأساسي ومايتشالش.',
                'icon' => 'PanelBottom',
                'pages' => ['footer'],
                'limit' => 1,
                'locked' => true,
                'fields' => [
                    ['key' => 'about', 'type' => 'textarea', 'label' => 'نبذة عن متجرك', 'default' => '', 'max' => 400,
                        'hint' => 'سيبها فاضية وهياخد الوصف من إعدادات المتجر.'],
                    ['key' => 'show_categories', 'type' => 'toggle', 'label' => 'اعرض أقسام منتجاتي', 'default' => true],
                    ['key' => 'columns', 'type' => 'repeater', 'label' => 'مجموعات روابط زيادة', 'default' => [], 'max' => 3,
                        'item_label' => 'title', 'fields' => [
                            ['key' => 'title', 'type' => 'text', 'label' => 'عنوان المجموعة', 'default' => '', 'max' => 60],
                            ['key' => 'links', 'type' => 'repeater', 'label' => 'الروابط', 'default' => [], 'max' => 6,
                                'item_label' => 'label', 'fields' => [
                                    ['key' => 'label', 'type' => 'text', 'label' => 'الكلام الظاهر', 'default' => '', 'max' => 60],
                                    ['key' => 'url', 'type' => 'link', 'label' => 'يودّي فين؟', 'default' => ''],
                                ]],
                        ]],
                ],
            ],

            'footer_social' => [
                'name' => 'حساباتك على السوشيال',
                'description' => 'أيقونات فيسبوك وإنستجرام وواتساب وتيك توك.',
                'icon' => 'Share2',
                'pages' => ['footer'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'الحسابات', 'default' => [], 'max' => 6,
                        'item_label' => 'platform', 'fields' => [
                            ['key' => 'platform', 'type' => 'select', 'label' => 'الموقع', 'default' => 'facebook', 'options' => [
                                ['value' => 'facebook', 'label' => 'فيسبوك'],
                                ['value' => 'instagram', 'label' => 'إنستجرام'],
                                ['value' => 'tiktok', 'label' => 'تيك توك'],
                                ['value' => 'whatsapp', 'label' => 'واتساب'],
                                ['value' => 'youtube', 'label' => 'يوتيوب'],
                            ]],
                            ['key' => 'url', 'type' => 'link', 'label' => 'لينك الحساب', 'default' => ''],
                        ]],
                ],
            ],

            'footer_note' => [
                'name' => 'آخر سطر في الصفحة',
                'description' => 'سطر الحقوق تحت خالص.',
                'icon' => 'Copyright',
                'pages' => ['footer'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'text', 'type' => 'text', 'label' => 'الكلام', 'default' => '', 'max' => 200,
                        'hint' => 'سيبها فاضية وهيكتب © والسنة واسم متجرك.'],
                ],
            ],
        ];
    }

    /** @return array<string,mixed>|null */
    public function find(string $type): ?array
    {
        return $this->all()[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return $this->find($type) !== null;
    }

    /**
     * The parts offerable on a page, in catalogue order.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forPage(string $page): array
    {
        $kind = self::kindOf($page);

        return collect($this->all())
            ->filter(fn (array $section) => in_array($kind, $section['pages'], true))
            ->map(fn (array $section, string $key) => [...$section, 'type' => $key])
            ->values()
            ->all();
    }

    public function allowedOn(string $type, string $page): bool
    {
        return in_array(self::kindOf($page), $this->find($type)['pages'] ?? [], true);
    }

    public function isLocked(string $type): bool
    {
        return (bool) ($this->find($type)['locked'] ?? false);
    }

    /**
     * A fresh part's settings, straight from the field defaults.
     *
     * @return array<string,mixed>
     */
    public function defaultSettings(string $type): array
    {
        $settings = [];

        foreach ($this->find($type)['fields'] ?? [] as $field) {
            $settings[$field['key']] = $field['default'] ?? null;
        }

        return $settings;
    }
}

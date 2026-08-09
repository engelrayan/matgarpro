<?php

namespace App\Services\Builder;

/**
 * Every section a merchant can put on a page, and every setting it takes.
 *
 * This is the single source of truth for four different things that would
 * otherwise drift apart within a month:
 *
 *  1. the catalogue the builder shows in "أضف قسم",
 *  2. the form controls rendered in the settings panel,
 *  3. the validation rules applied on save,
 *  4. the defaults a brand-new section starts with.
 *
 * All four are derived from the `fields` array below, so adding a setting is
 * one edit here plus one line in the section's blade — never a change in four
 * places where the fourth is the one that gets forgotten and lets unvalidated
 * merchant input through.
 *
 * Field types the builder knows how to render:
 *   text · textarea · richtext · number · toggle · select · color · image
 *   link · products · categories · datetime · repeater
 */
class SectionRegistry
{
    /** Pages that carry a section list. */
    public const PAGES = ['home', 'product', 'category', 'header', 'footer'];

    public static function pageLabel(string $page): string
    {
        return [
            'home' => 'الصفحة الرئيسية',
            'product' => 'صفحة المنتج',
            'category' => 'صفحة القسم',
            'header' => 'الهيدر',
            'footer' => 'الفوتر',
        ][$page] ?? $page;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function all(): array
    {
        return [
            // ── Home ──────────────────────────────────────────────────────
            'hero' => [
                'name' => 'الهيرو',
                'description' => 'أول حاجة الزبون بيشوفها. سلايدر صور أو منتجات.',
                'icon' => 'Presentation',
                'pages' => ['home'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'source', 'type' => 'select', 'label' => 'مصدر الشرايح', 'default' => 'auto',
                        'options' => [
                            ['value' => 'auto', 'label' => 'من المنتجات (الخصومات الأول)'],
                            ['value' => 'manual', 'label' => 'صور أرفعها بنفسي'],
                        ],
                        'hint' => 'لو اخترت «من المنتجات» مش هتحتاج ترفع حاجة — الهيرو بيتعمل لوحده.'],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'عدد الشرايح', 'default' => 3, 'min' => 1, 'max' => 8,
                        'when' => ['source' => 'auto']],
                    ['key' => 'slides', 'type' => 'repeater', 'label' => 'الشرايح', 'default' => [], 'max' => 8,
                        'when' => ['source' => 'manual'],
                        'item_label' => 'title',
                        'fields' => [
                            ['key' => 'image', 'type' => 'image', 'label' => 'الصورة', 'default' => null],
                            ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => '', 'max' => 120],
                            ['key' => 'subtitle', 'type' => 'text', 'label' => 'سطر تحت العنوان', 'default' => '', 'max' => 200],
                            ['key' => 'button_text', 'type' => 'text', 'label' => 'نص الزر', 'default' => 'اطلب دلوقتي', 'max' => 40],
                            ['key' => 'link', 'type' => 'link', 'label' => 'الزر يروح فين', 'default' => ''],
                        ]],
                    ['key' => 'autoplay', 'type' => 'toggle', 'label' => 'تحرك لوحدها', 'default' => true],
                    ['key' => 'interval', 'type' => 'number', 'label' => 'كل كام ثانية', 'default' => 6, 'min' => 2, 'max' => 30,
                        'when' => ['autoplay' => true]],
                ],
            ],

            'trust_bar' => [
                'name' => 'شريط الثقة',
                'description' => 'الدفع عند الاستلام، الشحن، الاستبدال — اعتراضات أول عملية شراء.',
                'icon' => 'ShieldCheck',
                'pages' => ['home', 'product', 'category'],
                'limit' => 2,
                'fields' => [
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'المزايا', 'default' => [
                        ['icon' => 'check', 'title' => 'الدفع عند الاستلام', 'body' => 'ادفع لما يوصلك'],
                        ['icon' => 'truck', 'title' => 'شحن لكل المحافظات', 'body' => 'من ٢ لـ ٥ أيام'],
                        ['icon' => 'refresh', 'title' => 'استبدال ١٤ يوم', 'body' => 'من غير أسئلة'],
                    ], 'max' => 4, 'item_label' => 'title', 'fields' => [
                        ['key' => 'icon', 'type' => 'select', 'label' => 'الأيقونة', 'default' => 'check', 'options' => [
                            ['value' => 'check', 'label' => 'صح'],
                            ['value' => 'truck', 'label' => 'شحن'],
                            ['value' => 'refresh', 'label' => 'استبدال'],
                            ['value' => 'shield', 'label' => 'ضمان'],
                            ['value' => 'phone', 'label' => 'اتصال'],
                            ['value' => 'wallet', 'label' => 'فلوس'],
                        ]],
                        ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => '', 'max' => 60],
                        ['key' => 'body', 'type' => 'text', 'label' => 'السطر الصغير', 'default' => '', 'max' => 80],
                    ]],
                ],
            ],

            'deals' => [
                'name' => 'العروض',
                'description' => 'المنتجات اللي عليها خصم شغّال، الأقرب انتهاءً الأول.',
                'icon' => 'Tag',
                'pages' => ['home'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => 'عروض النهارده', 'max' => 80],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'عدد المنتجات', 'default' => 8, 'min' => 2, 'max' => 20],
                ],
            ],

            'categories' => [
                'name' => 'الأقسام',
                'description' => 'كروت الأقسام. الأقسام الفاضية بتتخفي لوحدها.',
                'icon' => 'LayoutGrid',
                'pages' => ['home'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => 'تسوّق حسب القسم', 'max' => 80],
                    ['key' => 'columns', 'type' => 'select', 'label' => 'عدد الأعمدة', 'default' => '4', 'options' => [
                        ['value' => '2', 'label' => 'عمودين'],
                        ['value' => '3', 'label' => '٣ أعمدة'],
                        ['value' => '4', 'label' => '٤ أعمدة'],
                    ]],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'أقصى عدد', 'default' => 8, 'min' => 2, 'max' => 24],
                ],
            ],

            'featured_products' => [
                'name' => 'منتجات مختارة',
                'description' => 'أنت بتختار المنتجات بنفسك وبالترتيب اللي انت عايزه.',
                'icon' => 'Star',
                'pages' => ['home', 'product', 'category'],
                'limit' => 3,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => 'الأكثر مبيعاً', 'max' => 80],
                    ['key' => 'products', 'type' => 'products', 'label' => 'المنتجات', 'default' => [], 'max' => 12],
                    ['key' => 'columns', 'type' => 'select', 'label' => 'عدد الأعمدة', 'default' => '4', 'options' => [
                        ['value' => '2', 'label' => 'عمودين'],
                        ['value' => '3', 'label' => '٣ أعمدة'],
                        ['value' => '4', 'label' => '٤ أعمدة'],
                    ]],
                ],
            ],

            'product_grid' => [
                'name' => 'شبكة المنتجات',
                'description' => 'كل المنتجات أو قسم معيّن، بترتيب تختاره.',
                'icon' => 'Grid3x3',
                'pages' => ['home'],
                'limit' => 2,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => 'كل المنتجات', 'max' => 80],
                    ['key' => 'source', 'type' => 'select', 'label' => 'المصدر', 'default' => 'all', 'options' => [
                        ['value' => 'all', 'label' => 'كل المنتجات'],
                        ['value' => 'newest', 'label' => 'الأحدث'],
                        ['value' => 'discounted', 'label' => 'عليها خصم'],
                        ['value' => 'category', 'label' => 'قسم معيّن'],
                    ]],
                    ['key' => 'category', 'type' => 'categories', 'label' => 'القسم', 'default' => [], 'max' => 1,
                        'when' => ['source' => 'category']],
                    ['key' => 'columns', 'type' => 'select', 'label' => 'عدد الأعمدة', 'default' => '4', 'options' => [
                        ['value' => '2', 'label' => 'عمودين'],
                        ['value' => '3', 'label' => '٣ أعمدة'],
                        ['value' => '4', 'label' => '٤ أعمدة'],
                    ]],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'عدد المنتجات في الصفحة', 'default' => 24, 'min' => 4, 'max' => 48],
                    ['key' => 'paginate', 'type' => 'toggle', 'label' => 'صفحات للباقي', 'default' => true,
                        'hint' => 'اقفلها لو عايز عدد ثابت من المنتجات وخلاص.'],
                ],
            ],

            'banner' => [
                'name' => 'بانر صورة',
                'description' => 'صورة عريضة أو صورتين جنب بعض، كل واحدة برابط.',
                'icon' => 'Image',
                'pages' => ['home', 'product', 'category'],
                'limit' => 4,
                'fields' => [
                    ['key' => 'layout', 'type' => 'select', 'label' => 'الشكل', 'default' => 'full', 'options' => [
                        ['value' => 'full', 'label' => 'صورة واحدة عريضة'],
                        ['value' => 'split', 'label' => 'صورتين جنب بعض'],
                    ]],
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'الصور', 'default' => [], 'max' => 2,
                        'item_label' => 'headline', 'fields' => [
                            ['key' => 'image', 'type' => 'image', 'label' => 'الصورة', 'default' => null],
                            ['key' => 'headline', 'type' => 'text', 'label' => 'العنوان فوق الصورة', 'default' => '', 'max' => 100],
                            ['key' => 'sub', 'type' => 'text', 'label' => 'سطر تحته', 'default' => '', 'max' => 160],
                            ['key' => 'button_text', 'type' => 'text', 'label' => 'نص الزر', 'default' => '', 'max' => 40],
                            ['key' => 'link', 'type' => 'link', 'label' => 'الرابط', 'default' => ''],
                        ]],
                ],
            ],

            'rich_text' => [
                'name' => 'نص',
                'description' => 'قصة المتجر، سياسة الاستبدال، أي كلام منسّق.',
                'icon' => 'Type',
                'pages' => ['home', 'product', 'category', 'footer'],
                'limit' => 4,
                'fields' => [
                    ['key' => 'heading', 'type' => 'text', 'label' => 'العنوان', 'default' => '', 'max' => 100],
                    ['key' => 'body', 'type' => 'richtext', 'label' => 'النص', 'default' => ''],
                    ['key' => 'align', 'type' => 'select', 'label' => 'المحاذاة', 'default' => 'right', 'options' => [
                        ['value' => 'right', 'label' => 'يمين'],
                        ['value' => 'center', 'label' => 'وسط'],
                    ]],
                    ['key' => 'width', 'type' => 'select', 'label' => 'العرض', 'default' => 'narrow', 'options' => [
                        ['value' => 'narrow', 'label' => 'ضيّق (أسهل في القراية)'],
                        ['value' => 'wide', 'label' => 'عريض'],
                    ]],
                ],
            ],

            'faq' => [
                'name' => 'أسئلة شائعة',
                'description' => 'أسئلة وأجوبة بتتفتح بالضغط. بتقلل مكالمات التأكيد.',
                'icon' => 'MessageCircleQuestion',
                'pages' => ['home', 'product', 'category'],
                'limit' => 2,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => 'أسئلة شائعة', 'max' => 80],
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'الأسئلة', 'default' => [], 'max' => 15,
                        'item_label' => 'question', 'fields' => [
                            ['key' => 'question', 'type' => 'text', 'label' => 'السؤال', 'default' => '', 'max' => 200],
                            ['key' => 'answer', 'type' => 'textarea', 'label' => 'الإجابة', 'default' => '', 'max' => 1000],
                        ]],
                ],
            ],

            'video' => [
                'name' => 'فيديو',
                'description' => 'فيديو يوتيوب. بيتحمّل لما الزبون يوصله مش قبل كده.',
                'icon' => 'Youtube',
                'pages' => ['home', 'product', 'category'],
                'limit' => 2,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => '', 'max' => 100],
                    ['key' => 'url', 'type' => 'text', 'label' => 'رابط الفيديو', 'default' => '', 'max' => 300,
                        'hint' => 'الصق رابط يوتيوب عادي — هنستخرج الفيديو منه.'],
                    ['key' => 'caption', 'type' => 'text', 'label' => 'سطر تحت الفيديو', 'default' => '', 'max' => 200],
                ],
            ],

            'testimonials' => [
                'name' => 'آراء العملاء',
                'description' => 'رأي باسم وصورة وتقييم. أقوى من أي كلام عن نفسك.',
                'icon' => 'Quote',
                'pages' => ['home', 'product', 'category'],
                'limit' => 2,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => 'عملاؤنا بيقولوا', 'max' => 80],
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'الآراء', 'default' => [], 'max' => 12,
                        'item_label' => 'name', 'fields' => [
                            ['key' => 'name', 'type' => 'text', 'label' => 'الاسم', 'default' => '', 'max' => 80],
                            ['key' => 'city', 'type' => 'text', 'label' => 'المدينة', 'default' => '', 'max' => 60],
                            ['key' => 'rating', 'type' => 'number', 'label' => 'التقييم', 'default' => 5, 'min' => 1, 'max' => 5],
                            ['key' => 'text', 'type' => 'textarea', 'label' => 'الرأي', 'default' => '', 'max' => 500],
                            ['key' => 'image', 'type' => 'image', 'label' => 'صورة (اختياري)', 'default' => null],
                        ]],
                ],
            ],

            'countdown' => [
                'name' => 'عدّاد تنازلي',
                'description' => 'عرض بينتهي في وقت محدد. العدّاد بيختفي لوحده لما يخلص.',
                'icon' => 'Timer',
                'pages' => ['home', 'product', 'category'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => 'العرض ينتهي خلال', 'max' => 100],
                    ['key' => 'subtitle', 'type' => 'text', 'label' => 'سطر تحته', 'default' => '', 'max' => 160],
                    ['key' => 'ends_at', 'type' => 'datetime', 'label' => 'ينتهي إمتى', 'default' => null],
                    ['key' => 'button_text', 'type' => 'text', 'label' => 'نص الزر', 'default' => 'اطلب قبل ما يخلص', 'max' => 40],
                    ['key' => 'link', 'type' => 'link', 'label' => 'الزر يروح فين', 'default' => ''],
                    ['key' => 'style', 'type' => 'select', 'label' => 'الشكل', 'default' => 'solid', 'options' => [
                        ['value' => 'solid', 'label' => 'خلفية ملونة'],
                        ['value' => 'soft', 'label' => 'خفيف'],
                    ]],
                ],
            ],

            'brands' => [
                'name' => 'ماركات',
                'description' => 'شريط لوجوهات — ماركات بتبيعها أو جهات بتتعامل معاها.',
                'icon' => 'Sparkles',
                'pages' => ['home', 'product'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => '', 'max' => 80],
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'اللوجوهات', 'default' => [], 'max' => 12,
                        'item_label' => 'name', 'fields' => [
                            ['key' => 'image', 'type' => 'image', 'label' => 'اللوجو', 'default' => null],
                            ['key' => 'name', 'type' => 'text', 'label' => 'الاسم', 'default' => '', 'max' => 60],
                            ['key' => 'link', 'type' => 'link', 'label' => 'رابط (اختياري)', 'default' => ''],
                        ]],
                    ['key' => 'grayscale', 'type' => 'toggle', 'label' => 'أبيض وأسود', 'default' => true],
                ],
            ],

            // ── Product page ──────────────────────────────────────────────
            'product_main' => [
                'name' => 'المنتج وفورم الطلب',
                'description' => 'الصور والسعر وفورم الطلب. مينفعش يتشال.',
                'icon' => 'ShoppingCart',
                'pages' => ['product'],
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
                    ['key' => 'badge', 'type' => 'text', 'label' => 'شارة فوق الاسم', 'default' => '', 'max' => 40,
                        'hint' => 'زي «الأكثر مبيعاً». سيبها فاضية تختفي.'],
                ],
            ],

            'product_description' => [
                'name' => 'وصف المنتج',
                'description' => 'الوصف اللي كاتبه في صفحة المنتج نفسه.',
                'icon' => 'AlignRight',
                'pages' => ['product'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'heading', 'type' => 'text', 'label' => 'العنوان', 'default' => 'تفاصيل المنتج', 'max' => 80],
                ],
            ],

            'related_products' => [
                'name' => 'منتجات مشابهة',
                'description' => 'منتجات من نفس القسم، عشان الزبون ميخرجش من غير ما يشوف حاجة تانية.',
                'icon' => 'Shuffle',
                'pages' => ['product'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'العنوان', 'default' => 'ممكن يعجبك كمان', 'max' => 80],
                    ['key' => 'limit', 'type' => 'number', 'label' => 'العدد', 'default' => 4, 'min' => 2, 'max' => 12],
                ],
            ],

            // ── Category page ─────────────────────────────────────────────
            'category_header' => [
                'name' => 'عنوان القسم',
                'description' => 'اسم القسم ووصفه وعدد المنتجات.',
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
                'description' => 'منتجات القسم نفسه. مينفعش يتشال.',
                'icon' => 'Grid3x3',
                'pages' => ['category'],
                'limit' => 1,
                'locked' => true,
                'fields' => [
                    ['key' => 'columns', 'type' => 'select', 'label' => 'عدد الأعمدة', 'default' => '4', 'options' => [
                        ['value' => '2', 'label' => 'عمودين'],
                        ['value' => '3', 'label' => '٣ أعمدة'],
                        ['value' => '4', 'label' => '٤ أعمدة'],
                    ]],
                ],
            ],

            // ── Header ────────────────────────────────────────────────────
            'announcement_bar' => [
                'name' => 'شريط الإعلان',
                'description' => 'السطر الملوّن فوق خالص.',
                'icon' => 'Megaphone',
                'pages' => ['header'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'text', 'type' => 'text', 'label' => 'النص', 'max' => 160,
                        'default' => 'الدفع عند الاستلام · شحن لكل المحافظات · استبدال خلال ١٤ يوم'],
                    ['key' => 'link', 'type' => 'link', 'label' => 'رابط (اختياري)', 'default' => ''],
                    ['key' => 'style', 'type' => 'select', 'label' => 'اللون', 'default' => 'primary', 'options' => [
                        ['value' => 'primary', 'label' => 'لون المتجر'],
                        ['value' => 'dark', 'label' => 'غامق'],
                        ['value' => 'accent', 'label' => 'اللون المساعد'],
                    ]],
                ],
            ],

            'header_nav' => [
                'name' => 'الهيدر',
                'description' => 'اللوجو والقوايم والبحث.',
                'icon' => 'PanelTop',
                'pages' => ['header'],
                'limit' => 1,
                'locked' => true,
                'fields' => [
                    ['key' => 'sticky', 'type' => 'toggle', 'label' => 'يفضل ثابت مع النزول', 'default' => true],
                    ['key' => 'show_search', 'type' => 'toggle', 'label' => 'خانة البحث', 'default' => true],
                    ['key' => 'links_source', 'type' => 'select', 'label' => 'القوايم', 'default' => 'categories', 'options' => [
                        ['value' => 'categories', 'label' => 'أقسام المتجر تلقائي'],
                        ['value' => 'manual', 'label' => 'روابط أكتبها بنفسي'],
                        ['value' => 'none', 'label' => 'من غير قوايم'],
                    ]],
                    ['key' => 'links', 'type' => 'repeater', 'label' => 'الروابط', 'default' => [], 'max' => 6,
                        'when' => ['links_source' => 'manual'], 'item_label' => 'label', 'fields' => [
                            ['key' => 'label', 'type' => 'text', 'label' => 'الاسم', 'default' => '', 'max' => 40],
                            ['key' => 'url', 'type' => 'link', 'label' => 'الرابط', 'default' => ''],
                        ]],
                ],
            ],

            // ── Footer ────────────────────────────────────────────────────
            'footer_main' => [
                'name' => 'الفوتر',
                'description' => 'الأعمدة والروابط تحت.',
                'icon' => 'PanelBottom',
                'pages' => ['footer'],
                'limit' => 1,
                'locked' => true,
                'fields' => [
                    ['key' => 'about', 'type' => 'textarea', 'label' => 'نبذة عن المتجر', 'default' => '', 'max' => 400,
                        'hint' => 'سيبها فاضية وهياخد وصف المتجر من الإعدادات.'],
                    ['key' => 'show_categories', 'type' => 'toggle', 'label' => 'عمود الأقسام', 'default' => true],
                    ['key' => 'columns', 'type' => 'repeater', 'label' => 'أعمدة إضافية', 'default' => [], 'max' => 3,
                        'item_label' => 'title', 'fields' => [
                            ['key' => 'title', 'type' => 'text', 'label' => 'عنوان العمود', 'default' => '', 'max' => 60],
                            ['key' => 'links', 'type' => 'repeater', 'label' => 'الروابط', 'default' => [], 'max' => 6,
                                'item_label' => 'label', 'fields' => [
                                    ['key' => 'label', 'type' => 'text', 'label' => 'الاسم', 'default' => '', 'max' => 60],
                                    ['key' => 'url', 'type' => 'link', 'label' => 'الرابط', 'default' => ''],
                                ]],
                        ]],
                ],
            ],

            'footer_social' => [
                'name' => 'السوشيال',
                'description' => 'أيقونات فيسبوك وإنستجرام وواتساب.',
                'icon' => 'Share2',
                'pages' => ['footer'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'الحسابات', 'default' => [], 'max' => 6,
                        'item_label' => 'platform', 'fields' => [
                            ['key' => 'platform', 'type' => 'select', 'label' => 'المنصة', 'default' => 'facebook', 'options' => [
                                ['value' => 'facebook', 'label' => 'فيسبوك'],
                                ['value' => 'instagram', 'label' => 'إنستجرام'],
                                ['value' => 'tiktok', 'label' => 'تيك توك'],
                                ['value' => 'whatsapp', 'label' => 'واتساب'],
                                ['value' => 'youtube', 'label' => 'يوتيوب'],
                            ]],
                            ['key' => 'url', 'type' => 'link', 'label' => 'الرابط', 'default' => ''],
                        ]],
                ],
            ],

            'footer_note' => [
                'name' => 'سطر الحقوق',
                'description' => 'آخر سطر في الصفحة.',
                'icon' => 'Copyright',
                'pages' => ['footer'],
                'limit' => 1,
                'fields' => [
                    ['key' => 'text', 'type' => 'text', 'label' => 'النص', 'default' => '', 'max' => 200,
                        'hint' => 'سيبها فاضية وهيكتب © والسنة واسم المتجر.'],
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
     * The sections offerable on a page, in catalogue order.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forPage(string $page): array
    {
        return collect($this->all())
            ->filter(fn (array $section) => in_array($page, $section['pages'], true))
            ->map(fn (array $section, string $key) => [...$section, 'type' => $key])
            ->values()
            ->all();
    }

    public function allowedOn(string $type, string $page): bool
    {
        return in_array($page, $this->find($type)['pages'] ?? [], true);
    }

    public function isLocked(string $type): bool
    {
        return (bool) ($this->find($type)['locked'] ?? false);
    }

    /**
     * A fresh section's settings, straight from the field defaults.
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

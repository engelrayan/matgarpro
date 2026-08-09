<?php

/*
|--------------------------------------------------------------------------
| Theme showroom catalogue
|--------------------------------------------------------------------------
| The content each theme's demo store is filled with. One catalogue per theme
| tag so a watch theme shows watches and a kids theme shows kids' clothes —
| a merchant judges a theme by whether it suits THEIR product, and every demo
| showing the same three shirts answers that question for nobody.
|
| Prices are Egyptian and realistic. A demo priced at 9.99 reads as a template
| nobody finished.
|
| ── On the size of these lists ──────────────────────────────────────────────
| Fourteen products, not six. A showroom is the merchant's first look at what
| their own shop could be, and a grid holding six items on a 4-column layout
| ends after a row and a half — the page runs out before it has said anything.
| A full grid is the single cheapest way to make a theme look finished.
|
| Roughly six of each fourteen carry a `compare` price. That is deliberate too:
| the deals block only shows discounted items, so a catalogue with three of
| them renders a half-empty row right under the hero. It also keeps the ratio
| believable — a shop where everything is discounted is a shop nobody trusts.
|
| `name` is not free text: DemoArtwork::kindFor() picks the illustration by
| looking for a keyword in it (ساعة, قميص, حلل …). A product named outside that
| vocabulary silently falls back to a plain box, so keep the noun in the name.
| `hue` then tints that shape, which is what keeps fourteen of them from
| looking like fourteen copies.
*/

return [

    'catalogues' => [

        'watches' => [
            'store_name' => 'ساعات اللؤلؤة',
            'tagline' => 'ساعات أصلية بضمان سنتين',
            'categories' => ['ساعات رجالي', 'ساعات حريمي', 'محافظ جلد', 'عطور'],
            'products' => [
                ['name' => 'ساعة كرونوغراف ستانلس', 'price' => 8795, 'compare' => 10500, 'category' => 'ساعات رجالي', 'hue' => 210],
                ['name' => 'ساعة جلد بني كلاسيك', 'price' => 4250, 'compare' => null, 'category' => 'ساعات رجالي', 'hue' => 25],
                ['name' => 'ساعة ذهبي وأسود سبورت', 'price' => 6065, 'compare' => 7200, 'category' => 'ساعات رجالي', 'hue' => 45],
                ['name' => 'ساعة أوتوماتيك هيكل مفتوح', 'price' => 12400, 'compare' => null, 'category' => 'ساعات رجالي', 'hue' => 220],
                ['name' => 'ساعة غطس ٢٠٠ متر', 'price' => 7350, 'compare' => 8900, 'category' => 'ساعات رجالي', 'hue' => 195],
                ['name' => 'ساعة تيتانيوم خفيفة', 'price' => 9600, 'compare' => null, 'category' => 'ساعات رجالي', 'hue' => 205],
                ['name' => 'ساعة حريمي روز جولد', 'price' => 3890, 'compare' => null, 'category' => 'ساعات حريمي', 'hue' => 340],
                ['name' => 'ساعة حريمي سوار فضي', 'price' => 2950, 'compare' => 3600, 'category' => 'ساعات حريمي', 'hue' => 200],
                ['name' => 'ساعة حريمي مينا لؤلؤية', 'price' => 5200, 'compare' => 6400, 'category' => 'ساعات حريمي', 'hue' => 300],
                ['name' => 'ساعة حريمي جلد نبيتي', 'price' => 2450, 'compare' => null, 'category' => 'ساعات حريمي', 'hue' => 355],
                ['name' => 'محفظة جلد طبيعي أسود', 'price' => 1350, 'compare' => null, 'category' => 'محافظ جلد', 'hue' => 20],
                ['name' => 'محفظة جلد بني بحامل كروت', 'price' => 1180, 'compare' => 1490, 'category' => 'محافظ جلد', 'hue' => 30],
                ['name' => 'عطر عود ملكي ١٠٠ مل', 'price' => 2800, 'compare' => null, 'category' => 'عطور', 'hue' => 285],
                ['name' => 'عطر مسك أبيض ٥٠ مل', 'price' => 1450, 'compare' => 1850, 'category' => 'عطور', 'hue' => 45],
            ],
        ],

        'fashion' => [
            'store_name' => 'دار الأناقة',
            'tagline' => 'قطن مصري ١٠٠٪ · شحن لكل المحافظات',
            'categories' => ['رجالي', 'حريمي', 'أحذية'],
            'products' => [
                ['name' => 'قميص قطن مصري كلاسيك', 'price' => 599, 'compare' => 899, 'category' => 'رجالي', 'hue' => 210],
                ['name' => 'قميص كتان صيفي', 'price' => 680, 'compare' => null, 'category' => 'رجالي', 'hue' => 40],
                ['name' => 'بنطلون شينو كاجوال', 'price' => 750, 'compare' => null, 'category' => 'رجالي', 'hue' => 30],
                ['name' => 'بنطلون جينز سليم فيت', 'price' => 890, 'compare' => 1150, 'category' => 'رجالي', 'hue' => 215],
                ['name' => 'جاكيت جينز أوفر سايز', 'price' => 1200, 'compare' => 1550, 'category' => 'رجالي', 'hue' => 220],
                ['name' => 'جاكيت بومبر أسود', 'price' => 1390, 'compare' => null, 'category' => 'رجالي', 'hue' => 0],
                ['name' => 'فستان صيفي مشجّر', 'price' => 890, 'compare' => null, 'category' => 'حريمي', 'hue' => 340],
                ['name' => 'فستان سواريه طويل', 'price' => 2100, 'compare' => 2600, 'category' => 'حريمي', 'hue' => 280],
                ['name' => 'بلوزة ساتان', 'price' => 620, 'compare' => 780, 'category' => 'حريمي', 'hue' => 280],
                ['name' => 'بلوزة كروب تريكو', 'price' => 540, 'compare' => null, 'category' => 'حريمي', 'hue' => 160],
                ['name' => 'بنطلون واسع كتان حريمي', 'price' => 780, 'compare' => 950, 'category' => 'حريمي', 'hue' => 35],
                ['name' => 'حذاء رياضي أبيض', 'price' => 1450, 'compare' => null, 'category' => 'أحذية', 'hue' => 0],
                ['name' => 'حذاء رياضي جري خفيف', 'price' => 1680, 'compare' => 2050, 'category' => 'أحذية', 'hue' => 195],
                ['name' => 'حذاء جلد كلاسيك بني', 'price' => 1950, 'compare' => null, 'category' => 'أحذية', 'hue' => 25],
            ],
        ],

        'kids' => [
            'store_name' => 'دنيا الصغار',
            'tagline' => 'خامات آمنة على جلد طفلك · مقاسات من سنة لـ ١٢',
            'categories' => ['أولادي', 'بناتي', 'أحذية أطفال'],
            'products' => [
                ['name' => 'قميص أطفال قطن مشجّر', 'price' => 240, 'compare' => 320, 'category' => 'أولادي', 'hue' => 200],
                ['name' => 'قميص أولادي كاروهات', 'price' => 265, 'compare' => null, 'category' => 'أولادي', 'hue' => 15],
                ['name' => 'بنطلون أطفال جينز مطاط', 'price' => 310, 'compare' => 395, 'category' => 'أولادي', 'hue' => 220],
                ['name' => 'جاكيت أطفال مبطن', 'price' => 480, 'compare' => null, 'category' => 'أولادي', 'hue' => 30],
                ['name' => 'بنطلون أطفال ترينج', 'price' => 250, 'compare' => null, 'category' => 'أولادي', 'hue' => 265],
                ['name' => 'قميص أولادي بأكمام قصيرة', 'price' => 190, 'compare' => 245, 'category' => 'أولادي', 'hue' => 145],
                ['name' => 'فستان بناتي كشكش', 'price' => 420, 'compare' => 530, 'category' => 'بناتي', 'hue' => 335],
                ['name' => 'فستان بناتي سواريه صغير', 'price' => 650, 'compare' => null, 'category' => 'بناتي', 'hue' => 290],
                ['name' => 'بلوزة بناتي تريكو ملونة', 'price' => 275, 'compare' => null, 'category' => 'بناتي', 'hue' => 45],
                ['name' => 'فستان بناتي قطن يومي', 'price' => 330, 'compare' => 410, 'category' => 'بناتي', 'hue' => 180],
                ['name' => 'جاكيت بناتي بغطاء رأس', 'price' => 520, 'compare' => null, 'category' => 'بناتي', 'hue' => 320],
                ['name' => 'حذاء أطفال رياضي مريح', 'price' => 390, 'compare' => 480, 'category' => 'أحذية أطفال', 'hue' => 205],
                ['name' => 'حذاء أطفال بلاصق', 'price' => 340, 'compare' => null, 'category' => 'أحذية أطفال', 'hue' => 350],
                ['name' => 'حذاء بناتي لمّاع', 'price' => 420, 'compare' => 510, 'category' => 'أحذية أطفال', 'hue' => 300],
            ],
        ],

        'beauty' => [
            'store_name' => 'نضارة',
            'tagline' => 'منتجات عناية أصلية · نتيجة من أول أسبوع',
            'categories' => ['العناية بالبشرة', 'العناية بالشعر', 'عطور'],
            'products' => [
                ['name' => 'سيروم فيتامين سي ٢٠٪', 'price' => 450, 'compare' => 620, 'category' => 'العناية بالبشرة', 'hue' => 40],
                ['name' => 'سيروم ريتينول ليلي', 'price' => 520, 'compare' => null, 'category' => 'العناية بالبشرة', 'hue' => 285],
                ['name' => 'سيروم نياسيناميد ١٠٪', 'price' => 390, 'compare' => 495, 'category' => 'العناية بالبشرة', 'hue' => 150],
                ['name' => 'كريم ترطيب بحمض الهيالورونيك', 'price' => 380, 'compare' => null, 'category' => 'العناية بالبشرة', 'hue' => 190],
                ['name' => 'كريم عين مضاد للهالات', 'price' => 340, 'compare' => 430, 'category' => 'العناية بالبشرة', 'hue' => 215],
                ['name' => 'واقي شمس SPF 50', 'price' => 320, 'compare' => 410, 'category' => 'العناية بالبشرة', 'hue' => 50],
                ['name' => 'كريم مقشر ليلي بالـ AHA', 'price' => 410, 'compare' => null, 'category' => 'العناية بالبشرة', 'hue' => 330],
                ['name' => 'زيت أرجان للشعر', 'price' => 275, 'compare' => null, 'category' => 'العناية بالشعر', 'hue' => 35],
                ['name' => 'زيت جوز الهند للشعر', 'price' => 190, 'compare' => 240, 'category' => 'العناية بالشعر', 'hue' => 60],
                ['name' => 'شامبو خالي من السلفات', 'price' => 240, 'compare' => 310, 'category' => 'العناية بالشعر', 'hue' => 150],
                ['name' => 'شامبو للشعر المصبوغ', 'price' => 265, 'compare' => null, 'category' => 'العناية بالشعر', 'hue' => 300],
                ['name' => 'عطر عود شرقي ١٠٠ مل', 'price' => 1650, 'compare' => null, 'category' => 'عطور', 'hue' => 300],
                ['name' => 'عطر زهور الياسمين ٥٠ مل', 'price' => 890, 'compare' => 1100, 'category' => 'عطور', 'hue' => 45],
                ['name' => 'عطر مسك رجالي ١٠٠ مل', 'price' => 1250, 'compare' => null, 'category' => 'عطور', 'hue' => 200],
            ],
        ],

        'home' => [
            'store_name' => 'بيت وذوق',
            'tagline' => 'كل حاجة لبيتك في مكان واحد',
            'categories' => ['المطبخ', 'الديكور', 'المفروشات'],
            'products' => [
                ['name' => 'طقم حلل جرانيت ٩ قطع', 'price' => 3200, 'compare' => 4100, 'category' => 'المطبخ', 'hue' => 20],
                ['name' => 'طقم حلل ستانلس ١٢ قطعة', 'price' => 4600, 'compare' => null, 'category' => 'المطبخ', 'hue' => 205],
                ['name' => 'خلاط كهربائي ٧٠٠ وات', 'price' => 1850, 'compare' => null, 'category' => 'المطبخ', 'hue' => 210],
                ['name' => 'خلاط يدوي ٥ سرعات', 'price' => 780, 'compare' => 980, 'category' => 'المطبخ', 'hue' => 0],
                ['name' => 'طقم أكواب زجاج ٦ قطع', 'price' => 420, 'compare' => 550, 'category' => 'المطبخ', 'hue' => 190],
                ['name' => 'طقم أكواب شاي مذهّب', 'price' => 640, 'compare' => null, 'category' => 'المطبخ', 'hue' => 45],
                ['name' => 'مرآة حائط دائرية ذهبي', 'price' => 1100, 'compare' => null, 'category' => 'الديكور', 'hue' => 45],
                ['name' => 'مرآة أرضية طويلة', 'price' => 2350, 'compare' => 2900, 'category' => 'الديكور', 'hue' => 25],
                ['name' => 'مرآة حمام بإضاءة', 'price' => 1750, 'compare' => null, 'category' => 'الديكور', 'hue' => 195],
                ['name' => 'سجادة غرفة معيشة ٢×٣', 'price' => 2400, 'compare' => 2900, 'category' => 'المفروشات', 'hue' => 25],
                ['name' => 'سجادة مدخل صغيرة', 'price' => 650, 'compare' => null, 'category' => 'المفروشات', 'hue' => 260],
                ['name' => 'سجادة أطفال ملونة', 'price' => 980, 'compare' => 1250, 'category' => 'المفروشات', 'hue' => 150],
                ['name' => 'طقم مفارش قطن كينج', 'price' => 1650, 'compare' => null, 'category' => 'المفروشات', 'hue' => 260],
                ['name' => 'طقم مفارش صيفي مطبوع', 'price' => 1150, 'compare' => 1450, 'category' => 'المفروشات', 'hue' => 340],
            ],
        ],

    ],

    /*
    | Which catalogue each theme's showroom is filled with. A theme with no
    | entry falls back below — better a stocked shop in the wrong category than
    | an empty one.
    */
    'theme_catalogue' => [
        'jade' => 'beauty',
        'noir' => 'watches',
        'coral' => 'fashion',
        'indigo' => 'home',
        'rose' => 'beauty',
        'sand' => 'home',
        'pearl' => 'watches',
        'joy' => 'kids',
        'linen' => 'home',
    ],

    'fallback_catalogue' => 'fashion',

];

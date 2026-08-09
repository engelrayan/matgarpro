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
*/

return [

    'catalogues' => [

        'watches' => [
            'store_name' => 'ساعات اللؤلؤة',
            'tagline' => 'ساعات أصلية بضمان سنتين',
            'categories' => ['ساعات رجالي', 'ساعات حريمي', 'محافظ جلد'],
            'products' => [
                ['name' => 'ساعة كرونوغراف ستانلس', 'price' => 8795, 'compare' => 10500, 'category' => 'ساعات رجالي', 'hue' => 210],
                ['name' => 'ساعة جلد بني كلاسيك', 'price' => 4250, 'compare' => null, 'category' => 'ساعات رجالي', 'hue' => 25],
                ['name' => 'ساعة ذهبي وأسود سبورت', 'price' => 6065, 'compare' => 7200, 'category' => 'ساعات رجالي', 'hue' => 45],
                ['name' => 'ساعة حريمي روز جولد', 'price' => 3890, 'compare' => null, 'category' => 'ساعات حريمي', 'hue' => 340],
                ['name' => 'ساعة حريمي سوار فضي', 'price' => 2950, 'compare' => 3600, 'category' => 'ساعات حريمي', 'hue' => 200],
                ['name' => 'محفظة جلد طبيعي أسود', 'price' => 1350, 'compare' => null, 'category' => 'محافظ جلد', 'hue' => 20],
            ],
        ],

        'fashion' => [
            'store_name' => 'دار الأناقة',
            'tagline' => 'قطن مصري ١٠٠٪ · شحن لكل المحافظات',
            'categories' => ['رجالي', 'حريمي', 'أحذية'],
            'products' => [
                ['name' => 'قميص قطن مصري كلاسيك', 'price' => 599, 'compare' => 899, 'category' => 'رجالي', 'hue' => 210],
                ['name' => 'بنطلون شينو كاجوال', 'price' => 750, 'compare' => null, 'category' => 'رجالي', 'hue' => 30],
                ['name' => 'جاكيت جينز أوفر سايز', 'price' => 1200, 'compare' => 1550, 'category' => 'رجالي', 'hue' => 220],
                ['name' => 'فستان صيفي مشجّر', 'price' => 890, 'compare' => null, 'category' => 'حريمي', 'hue' => 340],
                ['name' => 'بلوزة ساتان', 'price' => 620, 'compare' => 780, 'category' => 'حريمي', 'hue' => 280],
                ['name' => 'حذاء رياضي أبيض', 'price' => 1450, 'compare' => null, 'category' => 'أحذية', 'hue' => 0],
            ],
        ],

        'beauty' => [
            'store_name' => 'نضارة',
            'tagline' => 'منتجات عناية أصلية · نتيجة من أول أسبوع',
            'categories' => ['العناية بالبشرة', 'العناية بالشعر', 'عطور'],
            'products' => [
                ['name' => 'سيروم فيتامين سي ٢٠٪', 'price' => 450, 'compare' => 620, 'category' => 'العناية بالبشرة', 'hue' => 40],
                ['name' => 'كريم ترطيب بحمض الهيالورونيك', 'price' => 380, 'compare' => null, 'category' => 'العناية بالبشرة', 'hue' => 190],
                ['name' => 'واقي شمس SPF 50', 'price' => 320, 'compare' => 410, 'category' => 'العناية بالبشرة', 'hue' => 50],
                ['name' => 'زيت أرجان للشعر', 'price' => 275, 'compare' => null, 'category' => 'العناية بالشعر', 'hue' => 35],
                ['name' => 'شامبو خالي من السلفات', 'price' => 240, 'compare' => 310, 'category' => 'العناية بالشعر', 'hue' => 150],
                ['name' => 'عطر عود شرقي ١٠٠ مل', 'price' => 1650, 'compare' => null, 'category' => 'عطور', 'hue' => 300],
            ],
        ],

        'home' => [
            'store_name' => 'بيت وذوق',
            'tagline' => 'كل حاجة لبيتك في مكان واحد',
            'categories' => ['المطبخ', 'الديكور', 'المفروشات'],
            'products' => [
                ['name' => 'طقم حلل جرانيت ٩ قطع', 'price' => 3200, 'compare' => 4100, 'category' => 'المطبخ', 'hue' => 20],
                ['name' => 'خلاط كهربائي ٧٠٠ وات', 'price' => 1850, 'compare' => null, 'category' => 'المطبخ', 'hue' => 210],
                ['name' => 'طقم أكواب زجاج ٦ قطع', 'price' => 420, 'compare' => 550, 'category' => 'المطبخ', 'hue' => 190],
                ['name' => 'مرآة حائط دائرية ذهبي', 'price' => 1100, 'compare' => null, 'category' => 'الديكور', 'hue' => 45],
                ['name' => 'سجادة غرفة معيشة ٢×٣', 'price' => 2400, 'compare' => 2900, 'category' => 'المفروشات', 'hue' => 25],
                ['name' => 'طقم مفارش قطن كينج', 'price' => 1650, 'compare' => null, 'category' => 'المفروشات', 'hue' => 260],
            ],
        ],
    ],

    /*
    | Which catalogue each theme's showroom uses. A theme with no entry falls
    | back to `fashion` — the widest catalogue, and the safest thing to show
    | for a theme whose intended niche we have not decided yet.
    */
    'theme_catalogue' => [
        'jade' => 'beauty',
        'noir' => 'watches',
        'coral' => 'fashion',
        'indigo' => 'home',
        'rose' => 'beauty',
        'sand' => 'home',
    ],

    'fallback_catalogue' => 'fashion',

];

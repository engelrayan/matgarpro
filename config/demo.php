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
| `photo` is a real product photograph: a Pexels photo id, stored once in
| resources/demo/photos/{id}.jpg (800×800, cropped square). Pexels' licence
| allows commercial use with no attribution. Photos were picked with no
| readable brand on them — a showroom must not look like it sells somebody
| else's trademark. Names follow the photo, not the other way round: a card
| that says "rose gold" over a silver watch reads as a broken shop.
|
| `hue` and the noun in `name` are the fallback: a row with no photo, or a
| photo file that went missing, is drawn by DemoArtwork instead (it picks the
| silhouette from a keyword in the name — ساعة, قميص, حلل … — and tints it).
*/

return [

    'catalogues' => [

        'watches' => [
            'store_name' => 'ساعات اللؤلؤة',
            'tagline' => 'ساعات أصلية بضمان سنتين',
            'categories' => ['ساعات رجالي', 'ساعات حريمي', 'محافظ جلد', 'عطور'],
            'products' => [
                ['name' => 'ساعة كرونوغراف جلد أسود', 'price' => 8795, 'compare' => 10500, 'category' => 'ساعات رجالي', 'hue' => 210, 'photo' => 28977357],
                ['name' => 'ساعة جلد بني كلاسيك', 'price' => 4250, 'compare' => null, 'category' => 'ساعات رجالي', 'hue' => 25, 'photo' => 13273982],
                ['name' => 'ساعة سبورت سوداء', 'price' => 6065, 'compare' => 7200, 'category' => 'ساعات رجالي', 'hue' => 45, 'photo' => 8839887],
                ['name' => 'ساعة أوتوماتيك هيكل مفتوح', 'price' => 12400, 'compare' => null, 'category' => 'ساعات رجالي', 'hue' => 220, 'photo' => 12835315],
                ['name' => 'ساعة ستانلس مينا زرقا', 'price' => 7350, 'compare' => 8900, 'category' => 'ساعات رجالي', 'hue' => 195, 'photo' => 16958879],
                ['name' => 'ساعة ستانلس مربعة', 'price' => 9600, 'compare' => null, 'category' => 'ساعات رجالي', 'hue' => 205, 'photo' => 23174727],
                ['name' => 'ساعة حريمي فضي بفصوص', 'price' => 3890, 'compare' => null, 'category' => 'ساعات حريمي', 'hue' => 340, 'photo' => 15210883],
                ['name' => 'ساعة حريمي سوار شبك', 'price' => 2950, 'compare' => 3600, 'category' => 'ساعات حريمي', 'hue' => 200, 'photo' => 9261531],
                ['name' => 'ساعة كلاسيك مينا بيضا', 'price' => 5200, 'compare' => 6400, 'category' => 'ساعات حريمي', 'hue' => 300, 'photo' => 12835312],
                ['name' => 'ساعة جلد بني مينا خضرا', 'price' => 2450, 'compare' => null, 'category' => 'ساعات حريمي', 'hue' => 355, 'photo' => 6157411],
                ['name' => 'محفظة جلد طبيعي هافان', 'price' => 1350, 'compare' => null, 'category' => 'محافظ جلد', 'hue' => 20, 'photo' => 12495669],
                ['name' => 'محفظة جلد بني بحامل كروت', 'price' => 1180, 'compare' => 1490, 'category' => 'محافظ جلد', 'hue' => 30, 'photo' => 12495664],
                ['name' => 'عطر عود ملكي ١٠٠ مل', 'price' => 2800, 'compare' => null, 'category' => 'عطور', 'hue' => 285, 'photo' => 28481966],
                ['name' => 'عطر مسك أبيض ٥٠ مل', 'price' => 1450, 'compare' => 1850, 'category' => 'عطور', 'hue' => 45, 'photo' => 16722452],
            ],
        ],

        'fashion' => [
            'store_name' => 'دار الأناقة',
            'tagline' => 'قطن مصري ١٠٠٪ · شحن لكل المحافظات',
            'categories' => ['رجالي', 'حريمي', 'أحذية'],
            'products' => [
                ['name' => 'قميص قطن مصري كلاسيك', 'price' => 599, 'compare' => 899, 'category' => 'رجالي', 'hue' => 210, 'photo' => 13381986],
                ['name' => 'قميص كتان صيفي', 'price' => 680, 'compare' => null, 'category' => 'رجالي', 'hue' => 40, 'photo' => 19915569],
                ['name' => 'بنطلون قماش أسود', 'price' => 750, 'compare' => null, 'category' => 'رجالي', 'hue' => 30, 'photo' => 7824050],
                ['name' => 'بنطلون جينز ٥ درجات', 'price' => 890, 'compare' => 1150, 'category' => 'رجالي', 'hue' => 215, 'photo' => 18533668],
                ['name' => 'جاكيت جينز فاتح', 'price' => 1200, 'compare' => 1550, 'category' => 'رجالي', 'hue' => 220, 'photo' => 3649765],
                ['name' => 'جاكيت بومبر أسود', 'price' => 1390, 'compare' => null, 'category' => 'رجالي', 'hue' => 0, 'photo' => 6062174],
                ['name' => 'فستان صيفي مشجّر', 'price' => 890, 'compare' => null, 'category' => 'حريمي', 'hue' => 340, 'photo' => 7131254],
                ['name' => 'فستان سواريه بكشكش', 'price' => 2100, 'compare' => 2600, 'category' => 'حريمي', 'hue' => 280, 'photo' => 18149120],
                ['name' => 'طقم ساتان أحمر', 'price' => 620, 'compare' => 780, 'category' => 'حريمي', 'hue' => 280, 'photo' => 13530707],
                ['name' => 'بلوزة كروب أخضر', 'price' => 540, 'compare' => null, 'category' => 'حريمي', 'hue' => 160, 'photo' => 5690228],
                ['name' => 'بنطلون واسع حريمي', 'price' => 780, 'compare' => 950, 'category' => 'حريمي', 'hue' => 35, 'photo' => 31400266],
                ['name' => 'حذاء رياضي أبيض', 'price' => 1450, 'compare' => null, 'category' => 'أحذية', 'hue' => 0, 'photo' => 27204251],
                ['name' => 'حذاء رياضي جري خفيف', 'price' => 1680, 'compare' => 2050, 'category' => 'أحذية', 'hue' => 195, 'photo' => 24702077],
                ['name' => 'حذاء جلد كلاسيك بني', 'price' => 1950, 'compare' => null, 'category' => 'أحذية', 'hue' => 25, 'photo' => 32644170],
            ],
        ],

        'kids' => [
            'store_name' => 'دنيا الصغار',
            'tagline' => 'خامات آمنة على جلد طفلك · مقاسات من سنة لـ ١٢',
            'categories' => ['أولادي', 'بناتي', 'أحذية أطفال'],
            'products' => [
                ['name' => 'طقم أطفال تيشيرت وشورت', 'price' => 240, 'compare' => 320, 'category' => 'أولادي', 'hue' => 200, 'photo' => 5693888],
                ['name' => 'طقم أولادي تيشيرت وترينج', 'price' => 310, 'compare' => 395, 'category' => 'أولادي', 'hue' => 220, 'photo' => 5693891],
                ['name' => 'طقم بيبي بطاقية', 'price' => 380, 'compare' => null, 'category' => 'بناتي', 'hue' => 145, 'photo' => 16222075],
                ['name' => 'فستان بناتي قطن وردي', 'price' => 330, 'compare' => 410, 'category' => 'بناتي', 'hue' => 335, 'photo' => 5693889],
                ['name' => 'فستان بناتي سواريه أحمر', 'price' => 650, 'compare' => null, 'category' => 'بناتي', 'hue' => 0, 'photo' => 29650590],
                ['name' => 'فستان بناتي بولكا', 'price' => 420, 'compare' => 530, 'category' => 'بناتي', 'hue' => 0, 'photo' => 6645065],
                ['name' => 'فستان بناتي كشكش', 'price' => 470, 'compare' => null, 'category' => 'بناتي', 'hue' => 335, 'photo' => 11100116],
                ['name' => 'فستان بناتي مشجّر يومي', 'price' => 290, 'compare' => 360, 'category' => 'بناتي', 'hue' => 180, 'photo' => 655625],
                ['name' => 'طقم بيبي توتو', 'price' => 360, 'compare' => null, 'category' => 'بناتي', 'hue' => 320, 'photo' => 2276589],
                ['name' => 'صندل أطفال وردي', 'price' => 290, 'compare' => 360, 'category' => 'أحذية أطفال', 'hue' => 335, 'photo' => 37305530],
                ['name' => 'صندل أطفال بيج', 'price' => 290, 'compare' => null, 'category' => 'أحذية أطفال', 'hue' => 40, 'photo' => 37242420],
            ],
        ],

        'beauty' => [
            'store_name' => 'نضارة',
            'tagline' => 'منتجات عناية أصلية · نتيجة من أول أسبوع',
            'categories' => ['العناية بالبشرة', 'العناية بالشعر', 'عطور'],
            'products' => [
                ['name' => 'سيروم فيتامين سي ٢٠٪', 'price' => 450, 'compare' => 620, 'category' => 'العناية بالبشرة', 'hue' => 40, 'photo' => 4841460],
                ['name' => 'سيروم ريتينول ليلي', 'price' => 520, 'compare' => null, 'category' => 'العناية بالبشرة', 'hue' => 285, 'photo' => 39452544],
                ['name' => 'سيروم نياسيناميد ١٠٪', 'price' => 390, 'compare' => 495, 'category' => 'العناية بالبشرة', 'hue' => 150, 'photo' => 4841179],
                ['name' => 'كريم ترطيب بحمض الهيالورونيك', 'price' => 380, 'compare' => null, 'category' => 'العناية بالبشرة', 'hue' => 190, 'photo' => 14473476],
                ['name' => 'كريم وش ليلي', 'price' => 340, 'compare' => 430, 'category' => 'العناية بالبشرة', 'hue' => 215, 'photo' => 10221858],
                ['name' => 'لوشن واقي شمس SPF 50', 'price' => 320, 'compare' => 410, 'category' => 'العناية بالبشرة', 'hue' => 50, 'photo' => 8015900],
                ['name' => 'غسول وش لطيف', 'price' => 280, 'compare' => null, 'category' => 'العناية بالبشرة', 'hue' => 330, 'photo' => 8015792],
                ['name' => 'زيت أرجان للشعر', 'price' => 275, 'compare' => null, 'category' => 'العناية بالشعر', 'hue' => 35, 'photo' => 14656279],
                ['name' => 'زيت جوز الهند للشعر', 'price' => 190, 'compare' => 240, 'category' => 'العناية بالشعر', 'hue' => 60, 'photo' => 36183642],
                ['name' => 'شامبو وبلسم خالي من السلفات', 'price' => 240, 'compare' => 310, 'category' => 'العناية بالشعر', 'hue' => 150, 'photo' => 33525723],
                ['name' => 'طقم شامبو وبلسم وجل', 'price' => 365, 'compare' => null, 'category' => 'العناية بالشعر', 'hue' => 300, 'photo' => 8634417],
                ['name' => 'عطر عود شرقي ١٠٠ مل', 'price' => 1650, 'compare' => null, 'category' => 'عطور', 'hue' => 300, 'photo' => 16722501],
                ['name' => 'عطر زهور الياسمين ٥٠ مل', 'price' => 890, 'compare' => 1100, 'category' => 'عطور', 'hue' => 45, 'photo' => 19644204],
                ['name' => 'عطر مسك رجالي ١٠٠ مل', 'price' => 1250, 'compare' => null, 'category' => 'عطور', 'hue' => 200, 'photo' => 16722499],
            ],
        ],

        'home' => [
            'store_name' => 'بيت وذوق',
            'tagline' => 'كل حاجة لبيتك في مكان واحد',
            'categories' => ['المطبخ', 'الديكور', 'المفروشات'],
            'products' => [
                ['name' => 'طاسة جرانيت ٢٨ سم', 'price' => 690, 'compare' => 890, 'category' => 'المطبخ', 'hue' => 20, 'photo' => 8916610],
                ['name' => 'خلاط كهربائي ٧٠٠ وات', 'price' => 1850, 'compare' => null, 'category' => 'المطبخ', 'hue' => 210, 'photo' => 17890636],
                ['name' => 'خلاط عصير بإبريق زجاج', 'price' => 1290, 'compare' => 1590, 'category' => 'المطبخ', 'hue' => 0, 'photo' => 3094227],
                ['name' => 'طقم أكواب كريستال ٦ قطع', 'price' => 420, 'compare' => 550, 'category' => 'المطبخ', 'hue' => 190, 'photo' => 14086822],
                ['name' => 'طقم كاسات عصير منقوشة', 'price' => 380, 'compare' => null, 'category' => 'المطبخ', 'hue' => 60, 'photo' => 13439100],
                ['name' => 'طقم كاسات شاي ١٢ قطعة', 'price' => 640, 'compare' => null, 'category' => 'المطبخ', 'hue' => 45, 'photo' => 15445468],
                ['name' => 'مرايات حائط شمسية ذهبي', 'price' => 1100, 'compare' => null, 'category' => 'الديكور', 'hue' => 45, 'photo' => 29149067],
                ['name' => 'طقم مرايات حائط دائرية', 'price' => 950, 'compare' => 1200, 'category' => 'الديكور', 'hue' => 210, 'photo' => 15269290],
                ['name' => 'مرآة ديكور فينتاج', 'price' => 1750, 'compare' => null, 'category' => 'الديكور', 'hue' => 25, 'photo' => 34145213],
                ['name' => 'سجادة كليم يدوي ٢×٣', 'price' => 2400, 'compare' => 2900, 'category' => 'المفروشات', 'hue' => 25, 'photo' => 38317548],
                ['name' => 'طقم مفارش قطن كينج', 'price' => 1650, 'compare' => null, 'category' => 'المفروشات', 'hue' => 40, 'photo' => 30761844],
                ['name' => 'طقم ملايات قطن أبيض', 'price' => 1150, 'compare' => 1450, 'category' => 'المفروشات', 'hue' => 0, 'photo' => 20468733],
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

    /*
    | Every photo in resources/demo/photos, by what it shows, in plain English.
    |
    | The AI store builder's starter products take their photo from here: the
    | photo whose words share most with the product's photo search wins, each
    | photo used once per shop (StarterPhotos::fromLibrary). A product sharing
    | no word with any photo gets a drawn placeholder instead: a coffee shop
    | shown a watch is worse than shown no photo. The model is given this
    | vocabulary (StorePlanWriter::brief), so its searches use words found here.
    |
    | Adding a kind of shop is a photo in resources/demo/photos/{id}.jpg (an
    | 800px square) and a line here.
    */
    'photo_library' => [
        212269 => 'bedding sheets white pillows bed home',
        278664 => 'candle scented soy jar home',
        301367 => 'makeup lipstick cosmetics',
        655625 => 'kids dress girl floral',
        774378 => 'incense burner bakhoor mabkhara brass oriental',
        947407 => 'phone smartphone charger mobile',
        2276589 => 'baby clothes tutu set girl',
        3094227 => 'blender juicer glass kitchen appliance',
        3394650 => 'headphones wireless white',
        3648977 => 'flowers bouquet roses lilies',
        3649765 => 'jacket denim jeans women clothes',
        4841179 => 'skincare serum niacinamide dropper',
        4841460 => 'skincare serum vitamin dropper bottle',
        5081914 => 'smartwatch watch black sport',
        5629138 => 'bedding comforter blanket beige bed home',
        5690228 => 'blouse top crop green women clothes',
        5693888 => 'kids clothes set tshirt shorts boy',
        5693889 => 'kids dress girl pink cotton',
        5693891 => 'kids clothes set tracksuit boy',
        5737286 => 'rings gold wedding jewelry',
        5926957 => 'coffee beans bags roasted',
        6062174 => 'jacket bomber black men clothes',
        6157411 => 'watch wristwatch leather brown green dial',
        6645065 => 'kids dress girl polka dots',
        6664421 => 'sweets pastry pistachio dessert',
        6803749 => 'cashew nuts nut snacks',
        6930879 => 'soap handmade bars natural',
        6936981 => 'coffee beans',
        7131254 => 'dress summer floral women clothes',
        7431636 => 'notebook journal paper stationery',
        7810600 => 'makeup lipstick cosmetics red',
        7824050 => 'trousers pants black men clothes',
        7827595 => 'soap handmade white gift box',
        7829483 => 'spices star anise cloves cinnamon',
        7900219 => 'coffee turkish pot ground',
        7989741 => 'phone smartphone case black mobile',
        8015792 => 'skincare cleanser face wash bottle',
        8015900 => 'skincare sunscreen lotion bottle',
        8346914 => 'electronics gadgets laptop earbuds',
        8634417 => 'shampoo conditioner gel haircare set',
        8839887 => 'watch wristwatch sport black men',
        8916610 => 'pan frying cookware granite kitchen',
        8996217 => 'dates fruit stuffed coconut sweets',
        9058878 => 'headphones white wired',
        9058883 => 'headphones black',
        9228574 => 'honey jars natural',
        9261531 => 'watch wristwatch women mesh bracelet',
        10114294 => 'ring diamond silver jewelry',
        10221858 => 'skincare cream face night jar',
        10899594 => 'coffee turkish pot tray brass',
        11100116 => 'kids dress girl ruffle',
        12123230 => 'plant houseplant pot small',
        12495664 => 'wallet leather card holder brown',
        12495669 => 'wallet leather men brown',
        12835312 => 'watch wristwatch classic white dial',
        12835315 => 'watch wristwatch automatic skeleton luxury',
        13273982 => 'watch wristwatch leather brown classic men',
        13381986 => 'shirt cotton men classic clothes',
        13439100 => 'glasses juice cups patterned kitchen',
        13530707 => 'satin set red women clothes pajamas',
        14086822 => 'glasses crystal cups set kitchen',
        14473476 => 'skincare cream moisturizer jar',
        14656279 => 'hair oil argan bottle haircare',
        14691503 => 'smartwatch watch black',
        15003379 => 'candle scented luxury jar home',
        15210883 => 'watch wristwatch women silver crystals',
        15269290 => 'mirror wall round set decor home',
        15445468 => 'tea glasses cups set kitchen',
        15660335 => 'cutlery fork spoon knife kitchen utensils',
        16222075 => 'baby clothes set hat newborn',
        16263605 => 'soap handmade white natural',
        16722405 => 'perfume fragrance small bottle travel',
        16722451 => 'perfume fragrance bottle box gift',
        16722452 => 'perfume musk fragrance bottle',
        16722498 => 'perfume fragrance spray bottle',
        16722499 => 'perfume fragrance bottle men musk',
        16722501 => 'perfume oud fragrance bottle oriental',
        16958879 => 'watch wristwatch steel blue men',
        17840020 => 'incense stick burner holder',
        17890636 => 'blender electric kitchen appliance',
        18149120 => 'dress evening ruffle women clothes',
        18435586 => 'almonds nuts nut snacks',
        18435590 => 'dates fruit dried',
        18533668 => 'jeans denim pants trousers clothes',
        19644204 => 'perfume jasmine fragrance bottle women flowers',
        19833253 => 'haircare shampoo bottles set travel',
        19845610 => 'sneakers shoes canvas black',
        19915569 => 'shirt linen summer men clothes',
        20324601 => 'plant succulent pot',
        20468733 => 'bedding sheets white cotton bed home',
        21235148 => 'necklace pendant silver emerald jewelry',
        23174727 => 'watch wristwatch steel square',
        24702077 => 'sneakers shoes running sport',
        24743542 => 'incense stick holder',
        25492111 => 'sneakers shoes sport women running',
        25533521 => 'makeup lipstick cosmetics pink',
        25547393 => 'coffee beans roasted',
        27046143 => 'handbag bag leather women',
        27174573 => 'handbag bag leather brown women',
        27204251 => 'sneakers shoes white sport',
        27204287 => 'bag crossbody leather brown',
        28481966 => 'perfume oud fragrance bottle luxury',
        28882157 => 'flowers bouquet roses pink',
        28933799 => 'rings gold jewelry',
        28977357 => 'watch wristwatch chronograph leather black men',
        29149067 => 'mirror wall gold sun decor home',
        29387140 => 'tea herbal flowers dried herbs box',
        29502139 => 'soap handmade bars colorful natural',
        29650590 => 'kids dress girl red party',
        29764355 => 'coffee turkish cup',
        29765813 => 'notebook stationery pink pens office',
        30709483 => 'dates arabic coffee tea set',
        30761844 => 'bedding sheets cotton bed home',
        30999130 => 'perfume fragrance gift set box women',
        31400266 => 'trousers pants wide women clothes',
        31406903 => 'smartwatch watch pink',
        31443060 => 'chocolate sweets assorted candy',
        32038446 => 'spices herbs assorted',
        32644170 => 'shoes leather brown classic men',
        32677205 => 'sunglasses brown women',
        32677214 => 'sunglasses aviator blue men',
        32677246 => 'sunglasses black case',
        33180700 => 'candle decorative gift home',
        33525723 => 'shampoo conditioner haircare bottles',
        33597709 => 'sneakers shoes collection boxes',
        34145213 => 'mirror vintage decor home',
        34444200 => 'necklace gold beads jewelry',
        34917509 => 'tea black loose leaves',
        35156983 => 'herbs dried marjoram spices jar',
        35828604 => 'cutlery spoons forks vintage kitchen utensils',
        36183642 => 'hair oil coconut bottle haircare',
        36631827 => 'cashew nuts nut bowl snacks',
        37242420 => 'kids sandals shoes beige',
        37305530 => 'kids sandals shoes pink girl',
        37309469 => 'walnuts nuts nut bowl',
        37793421 => 'notebook journal pens stationery',
        38132239 => 'sweets oriental baklava pastries tray',
        38317548 => 'rug kilim carpet handmade home',
        38632939 => 'plant houseplant pot green',
        38649173 => 'powerbank charger phone accessories mobile',
        38651677 => 'bedding sheets blue cotton bed home',
        38773865 => 'honey honeycomb natural',
        38821137 => 'haircare shampoo serum oil bottles',
        39281916 => 'oil essential oils bottles skincare natural',
        39452544 => 'skincare serum retinol night bottle',
        39490579 => 'plant cactus pot',
        39802922 => 'flowers gerbera vase',
        40397476 => 'sweets baklava box pistachio',
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme picker previews
    |--------------------------------------------------------------------------
    | The three products each theme's card shows on the settings screen, by
    | photo id. Picked by hand from that theme's own catalogue, and different
    | for themes that share one (jade and rose are both beauty) — nine cards
    | showing the same three serums would make nine themes look like one.
    |
    | Name and price come from the catalogue row with that photo. The images
    | are 360px copies in public/images/theme-previews — the 800px originals
    | are a quarter of a megabyte each, for a 120px thumbnail.
    */
    'theme_previews' => [
        'jade' => [4841460, 14473476, 16722501],
        'rose' => [19644204, 4841179, 10221858],
        'noir' => [28977357, 8839887, 12495669],
        'pearl' => [12835315, 9261531, 16722452],
        'coral' => [7131254, 27204251, 3649765],
        'indigo' => [17890636, 14086822, 3094227],
        'sand' => [38317548, 29149067, 30761844],
        'linen' => [15269290, 20468733, 13439100],
        'joy' => [5693889, 37305530, 5693888],
    ],

];

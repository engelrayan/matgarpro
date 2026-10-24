<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Governorates
    |--------------------------------------------------------------------------
    | Ordered by population rather than alphabetically: on a phone, the buyer
    | most likely to be scrolling this list lives in Cairo or Giza, and putting
    | them first removes a scroll from the highest-traffic path.
    |
    | Stored on the order as the Arabic name, not an id. Shipping partners each
    | keep their own governorate ids, so a name is the only value that survives
    | switching carriers.
    */
    'governorates' => [
        'القاهرة',
        'الجيزة',
        'الإسكندرية',
        'القليوبية',
        'الشرقية',
        'الدقهلية',
        'البحيرة',
        'المنيا',
        'الغربية',
        'سوهاج',
        'أسيوط',
        'المنوفية',
        'كفر الشيخ',
        'الفيوم',
        'بني سويف',
        'قنا',
        'أسوان',
        'دمياط',
        'الإسماعيلية',
        'الأقصر',
        'بورسعيد',
        'السويس',
        'مطروح',
        'شمال سيناء',
        'جنوب سيناء',
        'البحر الأحمر',
        'الوادي الجديد',
    ],

    /*
    |--------------------------------------------------------------------------
    | Shipping zones
    |--------------------------------------------------------------------------
    | How Egyptian couriers price delivery: by region, not by governorate. A
    | merchant sets four prices instead of twenty-seven, and overrides the odd
    | governorate their own courier charges differently.
    |
    | Every governorate above belongs to exactly one zone — a test holds that,
    | because a governorate in no zone would have no price and silently vanish
    | from the order form.
    |
    | Qalyubia sits in the Delta, not Greater Cairo, as the owner asked; a
    | merchant whose courier treats Shubra El-Kheima as Cairo overrides it.
    */
    'shipping_zones' => [
        'cairo' => [
            'label' => 'القاهرة الكبرى',
            'governorates' => ['القاهرة', 'الجيزة'],
        ],
        'delta' => [
            'label' => 'بحري والقناة',
            'governorates' => [
                'الإسكندرية', 'القليوبية', 'الشرقية', 'الدقهلية', 'البحيرة', 'الغربية',
                'المنوفية', 'كفر الشيخ', 'دمياط', 'الإسماعيلية', 'بورسعيد', 'السويس',
            ],
        ],
        'upper' => [
            'label' => 'الصعيد',
            'governorates' => [
                'الفيوم', 'بني سويف', 'المنيا', 'أسيوط', 'سوهاج', 'قنا', 'الأقصر', 'أسوان',
                'البحر الأحمر', 'الوادي الجديد',
            ],
        ],
        // Most couriers charge these more, and some do not reach them at all.
        'remote' => [
            'label' => 'المناطق البعيدة',
            'governorates' => ['مطروح', 'شمال سيناء', 'جنوب سيناء'],
        ],
    ],

];

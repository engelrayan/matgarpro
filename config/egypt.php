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

];

<?php

/*
|--------------------------------------------------------------------------
| Storefront themes
|--------------------------------------------------------------------------
| A theme is a palette, a type pairing and a handful of layout switches — not
| a separate template tree.
|
| That is a deliberate trade. Four template trees look impressive on a pricing
| page and then rot: a fix to the buy button has to be made four times, and the
| fourth one is always the one that gets missed. One template driven by tokens
| means every store gets every fix, and the visual difference between these is
| still total.
|
| `palette` values are raw `H S% L%` so Tailwind can compose them with opacity.
| Every theme must define the same keys — a missing one falls back to the
| platform's own palette and produces a store that looks half-styled.
*/

return [

    'default' => 'jade',

    'themes' => [

        'jade' => [
            'name' => 'يشم',
            'description' => 'أخضر عميق وذهبي. هادي وفخم، ينفع لأي منتج.',
            'tags' => ['متجر عام', 'هدايا', 'عطور'],
            'font' => 'ibm-plex-sans-arabic',
            'radius' => '0.75rem',
            'layout' => 'classic',
            'palette' => [
                'primary' => '167 83% 28%',
                'primary-foreground' => '158 60% 96%',
                'accent' => '43 64% 46%',
                'background' => '45 33% 98%',
                'foreground' => '160 18% 6%',
                'card' => '0 0% 100%',
                'muted' => '44 28% 95%',
                'muted-foreground' => '36 7% 44%',
                'border' => '42 20% 89%',
            ],
        ],

        'noir' => [
            'name' => 'نوار',
            'description' => 'أسود ونحاسي. مناسب للساعات والإكسسوارات والمنتجات الغالية.',
            'tags' => ['ساعات', 'مجوهرات', 'إكسسوارات'],
            'font' => 'ibm-plex-sans-arabic',
            'radius' => '0.25rem',
            'layout' => 'editorial',
            'palette' => [
                'primary' => '30 8% 12%',
                'primary-foreground' => '40 30% 96%',
                'accent' => '28 60% 52%',
                'background' => '40 14% 97%',
                'foreground' => '30 10% 8%',
                'card' => '0 0% 100%',
                'muted' => '36 12% 94%',
                'muted-foreground' => '30 6% 42%',
                'border' => '35 12% 88%',
            ],
        ],

        'coral' => [
            'name' => 'مرجان',
            'description' => 'برتقالي دافي وحيوي. بيشتغل كويس مع الملابس ومنتجات الأطفال.',
            'tags' => ['ملابس', 'أطفال', 'هدايا'],
            'font' => 'ibm-plex-sans-arabic',
            'radius' => '1.25rem',
            'layout' => 'playful',
            'palette' => [
                'primary' => '14 82% 54%',
                'primary-foreground' => '20 60% 98%',
                'accent' => '188 62% 42%',
                'background' => '30 40% 98%',
                'foreground' => '16 25% 12%',
                'card' => '0 0% 100%',
                'muted' => '24 34% 95%',
                'muted-foreground' => '18 10% 44%',
                'border' => '22 26% 90%',
            ],
        ],

        'indigo' => [
            'name' => 'نيلي',
            'description' => 'أزرق تقني ونظيف. مناسب للإلكترونيات والأجهزة.',
            'tags' => ['إلكترونيات', 'أجهزة', 'متجر عام'],
            'font' => 'ibm-plex-sans-arabic',
            'radius' => '0.625rem',
            'layout' => 'classic',
            'palette' => [
                'primary' => '243 62% 52%',
                'primary-foreground' => '240 60% 98%',
                'accent' => '187 74% 44%',
                'background' => '240 24% 98%',
                'foreground' => '240 20% 10%',
                'card' => '0 0% 100%',
                'muted' => '240 20% 95%',
                'muted-foreground' => '240 6% 45%',
                'border' => '240 16% 90%',
            ],
        ],

        'rose' => [
            'name' => 'وردي',
            'description' => 'وردي ناعم. للتجميل والعناية والمنتجات النسائية.',
            'tags' => ['تجميل', 'عناية', 'ملابس'],
            'font' => 'ibm-plex-sans-arabic',
            'radius' => '1.5rem',
            'layout' => 'playful',
            'palette' => [
                'primary' => '338 62% 48%',
                'primary-foreground' => '340 60% 98%',
                'accent' => '270 44% 56%',
                'background' => '340 40% 99%',
                'foreground' => '336 22% 12%',
                'card' => '0 0% 100%',
                'muted' => '336 30% 96%',
                'muted-foreground' => '330 8% 46%',
                'border' => '334 22% 92%',
            ],
        ],

        'sand' => [
            'name' => 'رملي',
            'description' => 'ألوان ترابية هادية. للمنتجات اليدوية والمنزل والديكور.',
            'tags' => ['هاند ميد', 'منزل', 'ديكور'],
            'font' => 'ibm-plex-sans-arabic',
            'radius' => '0.5rem',
            'layout' => 'editorial',
            'palette' => [
                'primary' => '25 34% 32%',
                'primary-foreground' => '40 40% 97%',
                'accent' => '96 22% 42%',
                'background' => '40 30% 97%',
                'foreground' => '28 18% 12%',
                'card' => '42 40% 99%',
                'muted' => '38 24% 93%',
                'muted-foreground' => '30 8% 42%',
                'border' => '36 18% 88%',
            ],
        ],

    ],

];

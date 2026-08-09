<?php

/*
|--------------------------------------------------------------------------
| Storefront themes
|--------------------------------------------------------------------------
| A theme is a palette, a typeface and a handful of layout switches — not a
| separate template tree.
|
| That is a deliberate trade. Four template trees look impressive on a pricing
| page and then rot: a fix to the buy button has to be made four times, and the
| fourth one is always the one that gets missed. One template driven by tokens
| means every store gets every fix, and the visual difference between these is
| still total.
|
| For that trade to hold, the switches have to actually do something. Every key
| below is read by something:
|
|   palette  → CSS custom properties (ThemeResolver::cssVariables)
|   radius   → --radius, which the whole Tailwind radius scale composes from
|   font     → the stylesheet the storefront loads, and --font-sans
|   header   → which header shape `sections/header_nav` renders
|   card     → which product-card shape `partials/product-card` renders
|
| `palette` values are raw `H S% L%` so Tailwind can compose them with opacity.
| Every theme must define the same keys — a missing one falls back to the
| platform's own palette and produces a store that looks half-styled.
*/

return [

    'default' => 'jade',

    /*
    |--------------------------------------------------------------------------
    | Typefaces
    |--------------------------------------------------------------------------
    | slug (what Bunny Fonts serves) → CSS family name (what the browser needs).
    |
    | A lookup rather than deriving one from the other: "ibm-plex-sans-arabic"
    | title-cases to "Ibm Plex Sans Arabic", which matches no font anywhere and
    | fails silently to the system stack — the worst kind of bug, because the
    | page still renders and just looks cheap.
    |
    | All of these carry a real Arabic cut. A Latin-only face with an Arabic
    | fallback is the classic giveaway of a template that was translated rather
    | than designed.
    */
    'fonts' => [
        'ibm-plex-sans-arabic' => 'IBM Plex Sans Arabic',
        'cairo' => 'Cairo',
        'tajawal' => 'Tajawal',
        'almarai' => 'Almarai',
        'el-messiri' => 'El Messiri',
        'markazi-text' => 'Markazi Text',
        'amiri' => 'Amiri',
        'changa' => 'Changa',
        'baloo-bhaijaan-2' => 'Baloo Bhaijaan 2',
        'reem-kufi' => 'Reem Kufi',
    ],

    'themes' => [

        'jade' => [
            'name' => 'يشم',
            'description' => 'أخضر عميق وذهبي. هادي وفخم، ينفع لأي منتج.',
            'tags' => ['متجر عام', 'هدايا', 'عطور'],
            'font' => 'ibm-plex-sans-arabic',
            'radius' => '0.75rem',
            'header' => 'classic',
            'card' => 'soft',
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
            'description' => 'أسود ونحاسي، لوجو في النص وزوايا حادة. للساعات والإكسسوارات الغالية.',
            'tags' => ['ساعات', 'مجوهرات', 'إكسسوارات'],
            'font' => 'el-messiri',
            'radius' => '0.25rem',
            'header' => 'centered',
            'card' => 'sharp',
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
            'description' => 'برتقالي دافي وحيوي وزوايا دايرية. بيشتغل كويس مع الملابس ومنتجات الأطفال.',
            'tags' => ['ملابس', 'أطفال', 'هدايا'],
            'font' => 'almarai',
            'radius' => '1.25rem',
            'header' => 'classic',
            'card' => 'soft',
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
            'description' => 'أزرق تقني ونظيف، وكروت بإطار. مناسب للإلكترونيات والأجهزة.',
            'tags' => ['إلكترونيات', 'أجهزة', 'متجر عام'],
            'font' => 'cairo',
            'radius' => '0.625rem',
            'header' => 'classic',
            'card' => 'frame',
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
            'description' => 'وردي ناعم ولوجو في النص. للتجميل والعناية والمنتجات النسائية.',
            'tags' => ['تجميل', 'عناية', 'ملابس'],
            'font' => 'almarai',
            'radius' => '1.5rem',
            'header' => 'centered',
            'card' => 'soft',
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
            'description' => 'ألوان ترابية وخط بحروف مذيّلة. للمنتجات اليدوية والمنزل والديكور.',
            'tags' => ['هاند ميد', 'منزل', 'ديكور'],
            'font' => 'markazi-text',
            'radius' => '0.5rem',
            'header' => 'classic',
            'card' => 'frame',
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

        /*
        |----------------------------------------------------------------------
        | Added 2026-08-09
        |----------------------------------------------------------------------
        | The first three that use the switches rather than the palette to do
        | the work. Put a store on `pearl` and then on `joy` and almost nothing
        | on screen is in the same place — different typeface, different header,
        | different card, different corner language.
        */

        'pearl' => [
            'name' => 'لؤلؤ',
            'description' => 'أبيض واسع وذهبي، زوايا مربعة وخط كلاسيكي. أهدى ثيم عندنا — للمنتج الغالي اللي محتاج مساحة.',
            'tags' => ['ساعات', 'مجوهرات', 'عطور', 'هدايا فاخرة'],
            'font' => 'amiri',
            // Square corners. Nothing says "expensive" like the absence of a
            // rounded edge — every discount app on the internet is rounded.
            'radius' => '0rem',
            'header' => 'centered',
            'card' => 'sharp',
            'layout' => 'editorial',
            'palette' => [
                'primary' => '38 30% 22%',
                'primary-foreground' => '40 40% 98%',
                'accent' => '40 58% 48%',
                'background' => '40 20% 99%',
                'foreground' => '35 14% 10%',
                'card' => '0 0% 100%',
                'muted' => '40 16% 96%',
                'muted-foreground' => '36 6% 46%',
                'border' => '38 14% 91%',
            ],
        ],

        'joy' => [
            'name' => 'بهجة',
            'description' => 'ألوان فاتحة ودافية وزوايا دايرية جداً وصور كبيرة. للأطفال واللعب والهدايا.',
            'tags' => ['أطفال', 'لعب', 'هدايا', 'حلويات'],
            'font' => 'baloo-bhaijaan-2',
            'radius' => '1.75rem',
            'header' => 'classic',
            // The picture is the product for this kind of shop — a toy sells
            // itself in a photo and gets nothing from a border around it.
            'card' => 'full',
            'layout' => 'playful',
            'palette' => [
                'primary' => '265 62% 56%',
                'primary-foreground' => '270 60% 99%',
                'accent' => '38 92% 58%',
                'background' => '48 60% 98%',
                'foreground' => '265 25% 14%',
                'card' => '0 0% 100%',
                'muted' => '270 40% 96%',
                'muted-foreground' => '265 10% 46%',
                'border' => '268 30% 91%',
            ],
        ],

        'linen' => [
            'name' => 'نقي',
            'description' => 'أبيض ورمادي وأخضر خفيف، تفاصيل أقل ما يمكن. لما يكون عندك منتج واحد أو منتجات قليلة.',
            'tags' => ['منتج واحد', 'إلكترونيات', 'متجر عام'],
            'font' => 'tajawal',
            'radius' => '0.375rem',
            // No search box, no category strip — a shop with four products has
            // nothing to search for, and the links are noise the visitor has
            // to read past to reach the buy button.
            'header' => 'minimal',
            'card' => 'sharp',
            'layout' => 'classic',
            'palette' => [
                'primary' => '158 42% 30%',
                'primary-foreground' => '150 40% 98%',
                'accent' => '158 30% 44%',
                'background' => '0 0% 100%',
                'foreground' => '210 12% 12%',
                'card' => '0 0% 100%',
                'muted' => '210 16% 97%',
                'muted-foreground' => '210 6% 48%',
                'border' => '210 14% 92%',
            ],
        ],

    ],

];

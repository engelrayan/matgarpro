<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Per-product page settings
    |--------------------------------------------------------------------------
    | Defaults for the toggles on a product's "إعدادات متقدمة" panel. Merged
    | over whatever the merchant saved, so a setting added later switches on
    | for every existing product without a data migration.
    |
    | Deliberately absent, and not by oversight: fake visitor counters, fake
    | stock counts, fake countdown timers, and sending the ad pixel a price
    | that is not the real one. The last is the worst of them — it corrupts the
    | merchant's own campaign optimisation before it is anything else.
    */
    'defaults' => [
        // Wording on the button. Merchants test this constantly and it is the
        // single highest-leverage string on the page.
        'buy_button_text' => 'اطلب دلوقتي',

        /*
         | Buy bar pinned to the bottom on phones. On by default: almost all
         | this traffic is mobile, the form sits below a tall image, and a
         | customer who has to scroll back up to buy frequently does not.
         */
        'sticky_buy_bar' => true,

        // Put the order form above the description. Suits a short, impulse
        // product; a considered purchase usually wants the pitch first.
        'form_before_description' => false,

        // Drop the store header — for a page whose only job is to convert ad
        // traffic, with nowhere else to click.
        'hide_header' => false,

        // Show "شحن مجاني" on this product.
        'free_shipping' => false,

        /*
         | Hide the product once stock hits zero instead of showing it as sold
         | out. Off by default: a sold-out page still earns SEO and still lets a
         | customer ask, while a vanished page reads as a broken link.
         */
        'hide_when_out_of_stock' => false,
    ],

];

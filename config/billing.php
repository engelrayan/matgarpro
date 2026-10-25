<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    | Single currency for now. Every stored amount is a decimal(12,2) in this
    | currency — no minor units, no per-store currency conversion yet.
    */
    'currency' => env('BILLING_CURRENCY', 'EGP'),

    /*
    |--------------------------------------------------------------------------
    | Fallback price per order
    |--------------------------------------------------------------------------
    | Used only when a store has no plan and no override — a safety net so a
    | misconfigured store is never charged an accidental amount. Resolution
    | order is: store override → plan → this value.
    */
    'default_price_per_order' => (float) env('BILLING_DEFAULT_PRICE_PER_ORDER', 0),

    /*
    |--------------------------------------------------------------------------
    | Introductory months
    |--------------------------------------------------------------------------
    | Every new store opens with this many months at a lower price per order.
    | These used to be free months; they stopped being free when every order
    | could carry a WhatsApp confirmation that costs us money to send.
    |
    | The clock is stamped onto the store at creation (`trial_ends_at`, a name
    | kept from the free-months days) rather than read from here at billing
    | time, so shortening the offer next week does not shorten anybody's.
    */
    'intro_months' => (int) env('BILLING_INTRO_MONTHS', env('BILLING_TRIAL_MONTHS', 3)),

    // Taken off the base order price during the introductory months: 0.75
    // becomes 0.50. After them, "we add a quarter to each".
    'intro_discount' => (float) env('BILLING_INTRO_DISCOUNT', 0.25),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp confirmation
    |--------------------------------------------------------------------------
    | Added to an order's price when a confirmation message is actually sent
    | for it — automatically or by the merchant's click, once per order. A
    | store whose orders are free (the founding plan, an operator's zero
    | override) pays nothing for these either.
    */
    'whatsapp_confirmation_fee' => (float) env('BILLING_WHATSAPP_CONFIRMATION_FEE', 0.50),

    /*
    | …priced by the channel it went out on, because they cost us differently:
    |
    |   meta   the platform's official number — Meta bills us per template
    |          message. 0.75: an order is 1.25 with it in the introductory
    |          months (0.50 + 0.75), 1.50 after.
    |   waha   the store's own number through our WAHA server — no per-message
    |          bill, only the server it runs on. 0.50: an order is 1.00 with
    |          it in the introductory months, 1.25 after.
    */
    'whatsapp_fees' => [
        'meta' => (float) env('BILLING_WHATSAPP_FEE_META', 0.75),
        'waha' => (float) env('BILLING_WHATSAPP_FEE_WAHA', 0.50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Topping up
    |--------------------------------------------------------------------------
    | Until there is online payment, a merchant picks an amount, transfers it
    | by InstaPay, sends the receipt on WhatsApp to the same number, and an
    | operator confirms the request. The merchant only ever picks from these
    | amounts — the same screen has to work unchanged when a payment gateway
    | arrives, and a gateway is never handed a number typed by hand.
    */
    'topup' => [
        'amounts' => [200, 500, 1000, 2000, 5000, 10000],
        'instapay_number' => env('BILLING_INSTAPAY_NUMBER', '01006262330'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit floor
    |--------------------------------------------------------------------------
    | How far a wallet may go below zero before new orders arrive hidden. The
    | store never stops selling — losing a COD order costs the merchant far
    | more than the pound we are owed — but past this point an order's
    | customer details wait for a top-up (Store::hidesNewOrders,
    | OrderUnlocker), and that is what makes topping up worth doing.
    */
    'overdraft_limit' => (float) env('BILLING_OVERDRAFT_LIMIT', 25),

    /*
    |--------------------------------------------------------------------------
    | Low balance warning
    |--------------------------------------------------------------------------
    | Balance at which the merchant starts seeing a top-up banner.
    */
    'low_balance_threshold' => (float) env('BILLING_LOW_BALANCE_THRESHOLD', 50),

    /*
    |--------------------------------------------------------------------------
    | Paid AI services
    |--------------------------------------------------------------------------
    | Taken from the wallet once per landing page actually written into the
    | builder — not per attempt, so a call that fails costs the merchant
    | nothing. Full price in the introductory months too: the discount is on
    | orders, and this is a service we pay for per use. 0 makes it free.
    */
    'ai_landing_page_price' => (float) env('BILLING_AI_LANDING_PAGE_PRICE', 50),

];

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
    | Credit floor
    |--------------------------------------------------------------------------
    | How far a wallet may go below zero before the store stops accepting
    | orders. A small buffer keeps a store selling through the minutes between
    | running out and topping up — losing a COD order costs the merchant far
    | more than the pound we are owed.
    */
    'overdraft_limit' => (float) env('BILLING_OVERDRAFT_LIMIT', 25),

    /*
    |--------------------------------------------------------------------------
    | Low balance warning
    |--------------------------------------------------------------------------
    | Balance at which the merchant starts seeing a top-up banner.
    */
    'low_balance_threshold' => (float) env('BILLING_LOW_BALANCE_THRESHOLD', 50),

];

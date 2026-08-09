<?php

return [

    /*
    |--------------------------------------------------------------------------
    | When to warn a merchant
    |--------------------------------------------------------------------------
    | Two refusals, not one. A single refusal is as often a wrong address or a
    | courier who never called as it is a bad customer, and a warning that
    | fires on noise is one merchants learn to click past.
    |
    | Both conditions must hold: someone who refused twice out of thirty is a
    | normal customer, not a risk.
    */
    'risky_refusals' => 2,
    'risky_rate' => 60,

    /*
    |--------------------------------------------------------------------------
    | How far back the record looks
    |--------------------------------------------------------------------------
    | A refusal from two years ago says nothing about today. Entries older than
    | this stop counting toward the aggregate.
    */
    'window_days' => 365,

];

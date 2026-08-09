<?php

return [

    /*
    |--------------------------------------------------------------------------
    | What a returned parcel costs the merchant
    |--------------------------------------------------------------------------
    | A return is not a zero. The merchant paid to ship it out and paid again
    | to get it back, and the carrier charges both legs whether the customer
    | took the parcel or not.
    |
    | A flat figure for now, and a deliberately conservative one — the real
    | number varies by governorate and carrier. Once shipments carry their own
    | cost from Daman this reads it per parcel instead of estimating.
    */
    'return_cost_per_parcel' => (float) env('PROFIT_RETURN_COST', 60),

];

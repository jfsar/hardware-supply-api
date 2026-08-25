<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checkout Reservations
    |--------------------------------------------------------------------------
    |
    | Stock is held for a checkout session for this many minutes before the
    | expiry pipeline releases it back to availability (FR-INV-007/008).
    | The release sweep runs every minute on the "inventory" queue.
    |
    */

    'reservation_ttl' => (int) env('CHECKOUT_RESERVATION_TTL', 15),

];

<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Credit Price Configuration
    |--------------------------------------------------------------------------
    |
    | The price of a single credit in HUF (Hungarian Forint).
    | This is used when a client books a class without an active pass.
    | The amount is added to their unpaid_balance.
    |
    */

    'credit_price_huf' => env('BOOKING_CREDIT_PRICE_HUF', 1000),

    /*
    |--------------------------------------------------------------------------
    | Cancellation Window
    |--------------------------------------------------------------------------
    |
    | The number of hours before a class starts that a client can cancel
    | their booking without penalty (and receive a refund/balance adjustment).
    |
    */

    'cancellation_window_hours' => env('BOOKING_CANCELLATION_WINDOW_HOURS', 24),

];

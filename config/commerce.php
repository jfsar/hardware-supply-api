<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Commerce Defaults
    |--------------------------------------------------------------------------
    |
    | Shared commerce-wide settings. All monetary values flow through the
    | integer minor-unit helpers in App\Support\Money (FR-PRICE-009).
    |
    */

    'currency' => env('COMMERCE_CURRENCY', 'PHP'),

    'cart' => [
        // Guest cart identity lifetime in days (cookie + carts.expires_at).
        'ttl_days' => (int) env('COMMERCE_CART_TTL_DAYS', 30),
    ],

    'tax' => [
        // When true, catalog prices already contain VAT and checkout extracts
        // it instead of adding it on top (SRS §61).
        'prices_include_vat' => (bool) env('COMMERCE_PRICES_INCLUDE_VAT', false),

        // Fallback tax class code used when a variant has none assigned.
        'default_tax_class_code' => env('COMMERCE_DEFAULT_TAX_CLASS', 'VAT-PH'),
    ],

    'idempotency' => [
        // How long a stored idempotent response may be replayed (minutes).
        'ttl_minutes' => (int) env('CHECKOUT_IDEMPOTENCY_TTL', 1440),
    ],

];

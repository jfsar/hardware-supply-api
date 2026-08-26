<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payments / Gateway (Phase 5)
    |--------------------------------------------------------------------------
    |
    | Provider-agnostic gateway configuration (SRS §19/§74). The rest of the
    | application depends only on App\Contracts\PaymentGateway; the concrete
    | adapter is chosen here. Secrets are read server-side only and must
    | never be serialized into responses, logs, or exports (FR-PAY-009).
    |
    */

    // Master switch for gateway-driven checkout methods. When false only
    // COD passes PaymentMethod::assertAvailable() — a deployment without
    // provider credentials must never accept card/e-wallet orders.
    'enabled' => (bool) env('PAYMENTS_ENABLED', false),

    // Explicit class-name override. When null, the binding falls back to
    // PayrexPaymentGateway when a secret key exists, otherwise the
    // FakePaymentGateway so local/CI environments stay credential-free.
    'gateway' => env('PAYMENTS_GATEWAY'),

    'fake_mode' => (bool) env('PAYMENTS_FAKE_MODE', false),

    // Deterministic FakePaymentGateway behavior: success | fail.
    'fake_outcome' => env('PAYMENTS_FAKE_OUTCOME', 'success'),

    'payrex' => [
        'secret_key' => env('PAYREX_SECRET_KEY'),
        'public_key' => env('PAYREX_PUBLIC_KEY'),
        'webhook_secret' => env('PAYREX_WEBHOOK_SECRET'),

        // Reject replayed webhook deliveries older than this (seconds).
        'webhook_tolerance' => (int) env('PAYREX_WEBHOOK_TOLERANCE', 300),
    ],

    'attempts' => [
        // Maximum gateway attempts per payment row before manual review.
        'max' => (int) env('PAYMENTS_MAX_ATTEMPTS', 3),

        // Exponential backoff (seconds) between attempts.
        'backoff' => [30, 120, 600],
    ],

    'queue' => env('PAYMENTS_QUEUE', 'payments'),

    // PaymentMethod enum value => provider payment-method identifiers.
    'methods' => [
        'card' => ['card'],
        'e_wallet' => ['gcash', 'maya'],
        'qr' => ['qrph'],
        'gateway' => ['card', 'gcash', 'maya', 'qrph'],
    ],

    // Customer-facing redirect targets handed to the hosted Checkout page.
    'redirect_urls' => [
        'success' => env('PAYMENTS_SUCCESS_URL', '/orders/{order}/paid'),
        'cancel' => env('PAYMENTS_CANCEL_URL', '/checkout'),
    ],

    'refunds' => [
        // Internal reason => provider reason (PayRex fixed set).
        'reasons' => [
            'requested_by_customer' => 'requested_by_customer',
            'product_out_of_stock' => 'product_out_of_stock',
            'damaged' => 'product_was_damaged',
            'wrong_item' => 'wrong_product_received',
            'service_not_provided' => 'service_not_provided',
            'fraudulent' => 'fraudulent',
            'others' => 'others',
        ],
    ],

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outbound Webhooks (Phase 8)
    |--------------------------------------------------------------------------
    |
    | Domain events fan out to merchant-configured HTTPS endpoints
    | (FR-NOTIF-003/004/005). Deliveries are persisted as an outbox stream
    | (webhook_deliveries) and worked by a dedicated queue so retries and
    | redelivery idempotency survive restarts. Secrets are stored
    | encrypted server-side and only ever returned once at creation.
    |
    */

    // Queue that DeliverWebhook jobs run on.
    'queue' => env('WEBHOOKS_QUEUE', 'webhooks'),

    // Max seconds to wait for a 2xx-eligible response from a subscriber.
    'http_timeout' => (int) env('WEBHOOKS_HTTP_TIMEOUT', 5),

    // Exponential retry backoff, in seconds, before marking a delivery
    // Exhausted. Each entry maps to one automatic retry.
    'retry_schedule' => [60, 300, 1800, 7200, 43200],

    // The api_version stamped on every envelope and matched against
    // subscriptions (webhook_subscriptions.api_version).
    'api_version' => env('WEBHOOKS_API_VERSION', '1.0'),

    // Event types an endpoint may subscribe to, keyed by the outbound
    // event_type string used in envelopes.
    'events' => [
        'order.created',
        'payment.succeeded',
        'order.shipped',
        'refund.completed',
    ],

];

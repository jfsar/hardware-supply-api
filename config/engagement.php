<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Engagement Defaults
    |--------------------------------------------------------------------------
    |
    | Settings that govern customer-experience features: recently viewed
    | retention, product comparison caps, and recommendation windows.
    |
    */

    'recently_viewed' => [
        // How many days a viewed product stays in the recent history (FR-DISC-002).
        'days' => (int) env('ENGAGEMENT_RECENTLY_VIEWED_DAYS', 30),
    ],

    'comparison' => [
        // Maximum products a single comparison may hold (409 beyond this).
        'max_items' => (int) env('ENGAGEMENT_COMPARISON_MAX_ITEMS', 4),
    ],

    'recommendations' => [
        // Maximum number of recommendations returned per request.
        'limit' => (int) env('ENGAGEMENT_RECOMMENDATIONS_LIMIT', 8),

        // Sales window (days) used for the "popular" signal.
        'popular_window_days' => (int) env('ENGAGEMENT_POPULAR_WINDOW_DAYS', 30),
    ],

];

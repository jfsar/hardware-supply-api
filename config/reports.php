<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reporting (Phase 8)
    |--------------------------------------------------------------------------
    |
    | Administration reports aggregate immutable order/payment/refund
    | snapshot columns over a completed financial state (FR-RPT-001).
    | Async exports stream CSV to a dedicated disk and are purged after a
    | retention window (NFR-DATA-002).
    |
    */

    // Filesystem disk used to store generated report export files.
    'disk' => env('REPORTS_DISK', 'local'),

    // How many days a completed export stays downloadable before the
    // purge sweep deletes the file (and its row).
    'export_ttl_days' => (int) env('REPORTS_EXPORT_TTL_DAYS', 7),

    // Number of minutes a signed download URL remains valid.
    'download_ttl_minutes' => (int) env('REPORTS_DOWNLOAD_TTL_MINUTES', 30),

    // Queue that report export jobs run on (separate from the financial
    // webhooks queue so slow CSV streams never starve payment callbacks).
    'queue' => env('REPORTS_QUEUE', 'reports'),

    // Default page size for list-shaped synchronous report results.
    'per_page' => (int) env('REPORTS_PER_PAGE', 100),

    // Date-range bounds: reports refuse windows wider than this many days.
    'max_range_days' => (int) env('REPORTS_MAX_RANGE_DAYS', 366),

];

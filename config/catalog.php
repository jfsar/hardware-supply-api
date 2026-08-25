<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Catalog Media
    |--------------------------------------------------------------------------
    |
    | Product images and documents are stored on an object-storage disk so
    | uploads survive redeploys and can be served through signed URLs. The
    | disk name must exist in config/filesystems.php ("r2" in production,
    | any local disk for development).
    |
    */

    'media_disk' => env('CATALOG_MEDIA_DISK', 'public'),

    'signed_url_minutes' => (int) env('CATALOG_SIGNED_URL_MINUTES', 15),

    'image' => [
        'max_kb' => (int) env('CATALOG_IMAGE_MAX_KB', 4096),
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
    ],

    'document' => [
        'max_kb' => (int) env('CATALOG_DOCUMENT_MAX_KB', 10240),
        'mimes' => ['pdf'],
    ],

];

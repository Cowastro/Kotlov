<?php

return [
    // Until order exchange is commissioned, CRM workflow must not depend on 1C mapping.
    'order_queue_enabled' => (bool) env('ONEC_ORDER_QUEUE_ENABLED', false),

    'exchange' => [
        'username' => env('ONEC_EXCHANGE_USER'),
        'password' => env('ONEC_EXCHANGE_PASSWORD'),
        // CommerceML treats file_limit as the maximum size of one HTTP chunk,
        // not as the maximum size of the complete XML file.
        'file_limit' => (int) env('ONEC_EXCHANGE_FILE_LIMIT', 10 * 1024 * 1024),
        'max_file_size' => (int) env('ONEC_EXCHANGE_MAX_FILE_SIZE', 512 * 1024 * 1024),
        'storage_disk' => env('ONEC_EXCHANGE_DISK', 'local'),
        'storage_path' => env('ONEC_EXCHANGE_PATH', 'onec-exchange'),
        // Keep the latest raw CommerceML files briefly for diagnostics and
        // mapping review. Older session directories are removed by scheduler.
        'file_retention_hours' => (int) env('ONEC_EXCHANGE_FILE_RETENTION_HOURS', 48),
    ],
];

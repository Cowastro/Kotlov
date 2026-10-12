<?php

return [
    'exchange' => [
        'username' => env('ONEC_EXCHANGE_USER'),
        'password' => env('ONEC_EXCHANGE_PASSWORD'),
        // CommerceML treats file_limit as the maximum size of one HTTP chunk,
        // not as the maximum size of the complete XML file.
        'file_limit' => (int) env('ONEC_EXCHANGE_FILE_LIMIT', 10 * 1024 * 1024),
        'max_file_size' => (int) env('ONEC_EXCHANGE_MAX_FILE_SIZE', 512 * 1024 * 1024),
        'storage_disk' => env('ONEC_EXCHANGE_DISK', 'local'),
        'storage_path' => env('ONEC_EXCHANGE_PATH', 'onec-exchange'),
    ],
];

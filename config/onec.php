<?php

return [
    'exchange' => [
        'username' => env('ONEC_EXCHANGE_USER'),
        'password' => env('ONEC_EXCHANGE_PASSWORD'),
        'file_limit' => (int) env('ONEC_EXCHANGE_FILE_LIMIT', 10 * 1024 * 1024),
        'storage_disk' => env('ONEC_EXCHANGE_DISK', 'local'),
        'storage_path' => env('ONEC_EXCHANGE_PATH', 'onec-exchange'),
    ],
];

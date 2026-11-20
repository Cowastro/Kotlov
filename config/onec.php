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

    // «Установка цен номенклатуры» the 1C agent creates from the site (ids of the 1C price types and currency).
    'price_documents' => [
        'currency_key' => '11867b41-09b8-11e9-bad6-1c1b0d1de7c2',
        'vat_rate' => 1.2,
        'types' => [
            // [kind, with VAT, 1C id, formula 1C keeps for a type computed from another]
            ['retail', false, '7b0fbdc2-1ffa-11e9-bad6-1c1b0d1de7c2', ''],
            ['wholesale', false, 'de262f38-66a1-11e9-9b7c-1c1b0d1de7c2', ''],
            ['wholesale', true, 'a56e299c-d38a-11e9-a218-1c1b0d1de7c2', '[ОптоваяЦена]*1.2'],
            ['retail', true, 'a56e299d-d38a-11e9-a218-1c1b0d1de7c2', '[РозничныйТипЦен]*1.2'],
        ],
    ],
];

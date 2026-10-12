<?php

return [
    'provider' => 'a1',
    'webhook_secret' => env('A1_TELEPHONY_WEBHOOK_SECRET', ''),
    'base_url' => env('A1_TELEPHONY_BASE_URL', 'https://vats.a1.by/crm-api/open-api/v1'),
    'timezone' => env('A1_TELEPHONY_TIMEZONE', 'Europe/Minsk'),
    'history_lookback_minutes' => (int) env('A1_TELEPHONY_HISTORY_LOOKBACK', 15),
];

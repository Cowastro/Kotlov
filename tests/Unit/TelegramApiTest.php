<?php

namespace Tests\Unit;

use App\Services\TelegramApi;
use Tests\TestCase;

class TelegramApiTest extends TestCase
{
    public function test_it_never_contacts_telegram_in_tests_by_default(): void
    {
        config([
            'services.telegram.bot_token' => 'production-looking-token',
            'services.telegram.allow_in_tests' => false,
        ]);

        $result = (new TelegramApi())->sendMessage('-100123456789', 'Не отправлять');

        $this->assertSame([
            'ok' => false,
            'skipped' => 'telegram_disabled_in_tests',
        ], $result);
    }
}

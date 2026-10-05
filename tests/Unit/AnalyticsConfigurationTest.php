<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnalyticsConfigurationTest extends TestCase
{
    #[DataProvider('publicLayouts')]
    public function test_yandex_metrika_is_managed_only_by_the_correct_gtm_container(string $layout): void
    {
        $contents = file_get_contents(resource_path('views/layouts/'.$layout));

        self::assertStringContainsString('GTM-5R24LNK', $contents);
        self::assertStringContainsString('counter 11162428 is managed by GTM-5R24LNK', $contents);
        self::assertStringNotContainsString('102254764', $contents);
        self::assertStringNotContainsString('mc.yandex.ru/metrika/tag.js', $contents);
    }

    public static function publicLayouts(): array
    {
        return [
            'legacy layout' => ['app.blade.php'],
            'current layout' => ['amerce.blade.php'],
        ];
    }
}

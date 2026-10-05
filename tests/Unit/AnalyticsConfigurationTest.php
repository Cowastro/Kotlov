<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnalyticsConfigurationTest extends TestCase
{
    #[DataProvider('publicLayouts')]
    public function test_public_layouts_use_the_new_owned_yandex_counter(string $layout): void
    {
        $contents = file_get_contents(resource_path('views/layouts/'.$layout));

        self::assertStringContainsString('partials.yandex-metrika-head', $contents);
        self::assertStringContainsString('partials.yandex-metrika-noscript', $contents);
        self::assertStringContainsString('671629ab0379a34d', $contents);
        self::assertStringNotContainsString('GTM-5R24LNK', $contents);
        self::assertStringNotContainsString('GTM-5G2F3ZT', $contents);
        self::assertStringNotContainsString('web.it-center.by/nw', $contents);
        self::assertStringNotContainsString('7a9017a97d9459a9', $contents);
    }

    public function test_new_yandex_counter_has_one_javascript_initializer_and_noscript_fallback(): void
    {
        $head = file_get_contents(resource_path('views/partials/yandex-metrika-head.blade.php'));
        $noscript = file_get_contents(resource_path('views/partials/yandex-metrika-noscript.blade.php'));

        self::assertStringContainsString("ym(113419397, 'init'", $head);
        self::assertStringContainsString('mc.yandex.ru/watch/113419397', $noscript);
        self::assertStringNotContainsString('11162428', $head.$noscript);
    }

    public static function publicLayouts(): array
    {
        return [
            'legacy layout' => ['app.blade.php'],
            'current layout' => ['amerce.blade.php'],
        ];
    }
}

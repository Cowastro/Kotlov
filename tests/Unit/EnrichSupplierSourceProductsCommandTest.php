<?php

namespace Tests\Unit;

use App\Console\Commands\EnrichSupplierSourceProductsCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class EnrichSupplierSourceProductsCommandTest extends TestCase
{
    #[DataProvider('qualityCases')]
    public function test_it_detects_only_content_that_needs_repair(
        string $content,
        string $shortDescription,
        bool $expected
    ): void {
        $command = new EnrichSupplierSourceProductsCommand();
        $method = new ReflectionMethod($command, 'hasContentQualityIssues');

        self::assertSame($expected, $method->invoke($command, $content, $shortDescription));
    }

    public static function qualityCases(): array
    {
        $goodContent = '<p>' . str_repeat('Подтверждённое описание производителя с назначением и особенностями монтажа. ', 4) . '</p>';
        $goodShort = str_repeat('Краткое описание официальной модели и её ключевого назначения. ', 2);

        return [
            'complete source content' => [$goodContent, $goodShort, false],
            'thin content' => ['<p>Короткое описание.</p>', $goodShort, true],
            'thin short description' => [$goodContent, 'Коротко.', true],
            'legacy sales phrase' => [$goodContent . '<p>Вы можете купить данный товар у нас.</p>', $goodShort, true],
            'embedded source link' => [$goodContent . '<a href="https://example.com">Источник</a>', $goodShort, true],
        ];
    }
}

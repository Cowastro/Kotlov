<?php

namespace Tests\Unit;

use App\Console\Commands\SyncLigmetCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SyncLigmetCommandTest extends TestCase
{
    public function test_pegas_section_is_included_in_ligmet_brand_mapping(): void
    {
        $command = new SyncLigmetCommand;
        $method = new ReflectionMethod($command, 'brandFromSection');

        $this->assertSame('Пегас', $method->invoke($command, '03) Печи / Пегас, Россия'));
        $this->assertSame('Пегас', $method->invoke($command, '03) Stoves / PEGAS'));
    }

    #[DataProvider('numericStockProvider')]
    public function test_numeric_stock_is_authoritative(float $quantity, string $text, array $expected): void
    {
        $command = new SyncLigmetCommand;
        $method = new ReflectionMethod($command, 'stock');

        $this->assertSame($expected, $method->invoke($command, $quantity, $text));
    }

    public static function numericStockProvider(): array
    {
        return [
            'positive quantity' => [9.0, 'В наличии', [
                'status' => 'in_stock',
                'in_stock' => true,
                'quantity' => 9,
                'delivery_days' => 0,
            ]],
            'zero overrides stale available text' => [0.0, 'В наличии', [
                'status' => 'out_of_stock',
                'in_stock' => false,
                'quantity' => 0,
                'delivery_days' => null,
            ]],
            'low stock keeps exact quantity' => [2.0, 'Заканчивается', [
                'status' => 'low_stock',
                'in_stock' => true,
                'quantity' => 2,
                'delivery_days' => 0,
            ]],
        ];
    }
}

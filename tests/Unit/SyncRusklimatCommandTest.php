<?php

namespace Tests\Unit;

use App\Console\Commands\SyncRusklimatCommand;
use ReflectionClass;
use Tests\TestCase;

class SyncRusklimatCommandTest extends TestCase
{
    public function test_it_prefers_rusklimat_code_and_detects_opt_price(): void
    {
        $map = $this->invoke('detectColumns', [[
            'IMAGE', 'Наименование', 'Описание', 'Артикул', 'Размер/подключение',
            'Цвет', 'Остаток', 'РРЦ', 'ОПТ', 'Код',
        ]]);

        $this->assertSame(9, $map['article']);
        $this->assertSame(8, $map['price']);
        $this->assertSame(7, $map['retail_price']);
        $this->assertSame(6, $map['quantity']);
    }

    public function test_only_linked_mode_does_not_infer_a_new_product_relation(): void
    {
        $command = $this->command();
        $reflection = new ReflectionClass($command);
        $property = $reflection->getProperty('indexBySku');
        $property->setValue($command, ['НС-123' => 77]);

        $row = [
            'norm_article' => 'НС-123',
            'brand' => '',
            'name' => 'Тестовый товар',
        ];

        $this->assertNull($this->invoke('matchProduct', [$row, true]));
        $this->assertSame(77, $this->invoke('matchProduct', [$row, false])['product_id']);
    }

    public function test_it_skips_every_row_when_a_supplier_code_is_duplicated(): void
    {
        $rows = $this->invoke('normaliseRawRows', [[
            ['Группа', 'Модель', 'Наличие,шт', 'Розница, BYN', 'Дилер, BYN', 'Код НС', 'Бренд'],
            ['Обогреватели', 'BFT/PL old', '0', '28', '22,40', 'НС-1617961', 'Ballu'],
            ['Обогреватели', 'BFT/PL new', '19', '30', '24,00', 'НС-1617961', 'Ballu'],
        ]]);

        $this->assertCount(2, $rows);
        $this->assertSame('skipped_duplicate', $rows[0]['_action']);
        $this->assertSame('skipped_duplicate', $rows[1]['_action']);
    }

    private ?SyncRusklimatCommand $instance = null;

    private function command(): SyncRusklimatCommand
    {
        return $this->instance ??= new SyncRusklimatCommand;
    }

    private function invoke(string $method, array $arguments): mixed
    {
        $method = (new ReflectionClass($this->command()))->getMethod($method);

        return $method->invokeArgs($this->command(), $arguments);
    }
}

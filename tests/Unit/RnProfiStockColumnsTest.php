<?php

namespace Tests\Unit;

use App\Console\Commands\SyncRnProfiCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class RnProfiStockColumnsTest extends TestCase
{
    private function parse(array $header, array $row, string $sheet = 'csv'): array
    {
        return (new ReflectionMethod(SyncRnProfiCommand::class, 'normaliseSheetRows'))->invoke(new SyncRnProfiCommand, $sheet, [$header, $row])[0];
    }

    private function classify(array $row): array
    {
        return (new ReflectionMethod(SyncRnProfiCommand::class, 'classify'))->invoke(new SyncRnProfiCommand, $row);
    }

    public function test_packaging_in_csv_is_not_stock_even_without_a_brand_or_sheet_name(): void
    {
        $row = $this->parse(['Артикул', 'Наименование', 'Количество в упаковке', 'ОПТ', 'РРЦ'], ['VM04001', 'Клапан', '10', '12.50', '15']);
        $this->assertNull($row['qty']);
        $this->assertSame(10.0, $row['package_quantity']);
        $this->assertTrue($row['qty_is_package']);
        $this->assertSame('unknown', $this->classify($row)['stock']['status']);
    }

    public function test_explicit_warehouse_quantity_and_packaging_remain_separate(): void
    {
        $row = $this->parse(['Артикул', 'Наименование', 'Кол-во в упаковке', 'Остаток', 'ОПТ'], ['VM04001', 'Клапан', '100', '7', '12.50'], 'Varmega');
        $this->assertSame(7.0, $row['qty']);
        $this->assertSame(100.0, $row['package_quantity']);
        $this->assertFalse($row['qty_is_package']);
        $this->assertTrue($this->classify($row)['stock']['in_stock']);
    }

    public function test_merged_csv_section_heading_does_not_hide_the_size_column(): void
    {
        $row = $this->parse(['Радиаторная арматура VARMEGA Ручной радиаторный клапан', 'Артикул', 'Размер', 'Количество в упаковке', 'ОПТ', 'РРЦ'], ['', 'VM10001', '1/2', '1', '19.69', '24.19']);
        $this->assertSame('VM10001', $row['article']);
        $this->assertSame('1/2', $row['name']);
        $this->assertSame(1.0, $row['package_quantity']);
        $this->assertNull($row['qty']);
    }

    public function test_packaging_cannot_override_the_textual_absence_of_goods(): void
    {
        $row = $this->parse(['Артикул', 'Наименование', 'Количество в упаковке', 'Наличие', 'ОПТ'], ['VM04001', 'Клапан', '100', 'Нет в наличии', '12.50']);
        $this->assertSame('out_of_stock', $this->classify($row)['stock']['status']);
        // Previously generated rows/decisions must be safe too.
        $row['qty'] = 100;
        $this->assertSame('out_of_stock', $this->classify($row)['stock']['status']);
    }

    public function test_radiator_section_title_is_not_selected_instead_of_populated_models(): void
    {
        $rows = [['Алюминиевые радиаторы', 'Секционные радиаторы ROYAL THERMO', 'Наличие', 'ОПТ', 'РРЦ']];
        for ($i = 1; $i <= 5; $i++) $rows[] = ['', 'Revolution radiator '.$i, 'В наличии', '30.80', '36.60'];
        $parsed = (new ReflectionMethod(SyncRnProfiCommand::class, 'normaliseSheetRows'))->invoke(new SyncRnProfiCommand, '1. Радиаторы секционные БП', $rows);
        $this->assertCount(5, $parsed);
        $this->assertSame('Revolution radiator 1', $parsed[0]['name']);
        $this->assertSame('В наличии', $parsed[0]['stock_text']);
    }

    #[DataProvider('stockCases')]
    public function test_stock_evidence(string $text, ?float $quantity, string $expected): void
    {
        $stock = (new ReflectionMethod(SyncRnProfiCommand::class, 'stock'))->invoke(new SyncRnProfiCommand, $text, $quantity);
        $this->assertSame($expected, $stock['status']);
    }

    public static function stockCases(): array
    {
        return [
            ['', null, 'unknown'], ['', 7, 'in_stock'], ['', 0, 'out_of_stock'],
            ['В наличии', null, 'in_stock'], ['Нет в наличии', 10, 'out_of_stock'],
            ['Под заказ', 10, 'preorder'], ['Ожидается', 10, 'expected'],
            ['В наличии', 0, 'out_of_stock'],
        ];
    }
}

<?php

namespace Tests\Unit;

use App\Console\Commands\SyncThermokraftCommand;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class SyncThermokraftCommandTest extends TestCase
{
    #[Test]
    public function it_keeps_source_wholesale_retail_and_exact_stock_separate(): void
    {
        $csv = implode("\n", [
            'Наименование,Артикул,Штрихкод,,Опт,РРЦ,Количество,Гарантия,Бренд,Страна,Описание',
            'Котел UNIVAT Econom 6 кВт,UV-E6,4812345678901,,1000,1150,>5,24 месяца,UNIVAT,Беларусь,Электрический котел',
        ]);

        $rows = $this->parse($csv, '2059074837', 'boiler');

        $this->assertCount(1, $rows);
        $this->assertSame(1000.0, $rows[0]['wholesale']);
        $this->assertSame(1150.0, $rows[0]['retail']);
        $this->assertSame(6, $rows[0]['stock_quantity']);
        $this->assertTrue($rows[0]['in_stock']);
        $this->assertSame('UV-E6', $rows[0]['article']);
    }

    #[Test]
    public function it_imports_only_the_requested_brands_for_each_sheet(): void
    {
        $csv = implode("\n", [
            'Наименование,Артикул,Штрихкод,,Опт,РРЦ,Количество,Гарантия,Бренд,Страна,Описание',
            'ТЭНБ 6 кВт,,,,200,230,0,12 месяцев,СТЭН,Беларусь,Блок ТЭНов',
            'Насос 25-40,N-1,,,300,350,4,12 месяцев,ДРУГОЙ,Китай,Насос',
        ]);

        $rows = $this->parse($csv, '1999481119', 'heater');

        $this->assertCount(1, $rows);
        $this->assertSame('СТЭН', $rows[0]['brand']);
        $this->assertSame(0, $rows[0]['stock_quantity']);
        $this->assertFalse($rows[0]['in_stock']);
        $this->assertStringStartsWith('СТЭН-', $rows[0]['article']);
    }

    /** @return array<int,array<string,mixed>> */
    private function parse(string $csv, string $gid, string $kind): array
    {
        $method = new ReflectionMethod(SyncThermokraftCommand::class, 'parseCsv');
        $method->setAccessible(true);

        return $method->invoke(new SyncThermokraftCommand, $csv, $gid, $kind);
    }
}

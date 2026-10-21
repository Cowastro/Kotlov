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

    #[Test]
    public function it_imports_regler_automation_and_marks_an_invalid_retail_price(): void
    {
        $csv = implode("\n", [
            'Наименование,Артикул,Штрихкод,,Опт,РРЦ,Количество,Гарантия,Бренд,Страна,Описание',
            'Контроллер ReglerPro Wifi,,,,305,359,0,12,REGLER PRO,Россия,',
            'Вентилятор VSK 120,,,,313,265,0,12,REGLER PRO,Россия,',
            'Контроллер другого бренда,,,,200,250,2,12,TECH,Польша,',
        ]);

        $rows = $this->parse($csv, '494936167', 'automation');

        $this->assertCount(2, $rows);
        $this->assertSame('REGLER PRO', $rows[0]['brand']);
        $this->assertTrue($rows[0]['valid_price']);
        $this->assertFalse($rows[1]['valid_price']);
        $this->assertSame(313.0, $rows[1]['wholesale']);
        $this->assertSame(265.0, $rows[1]['retail']);
    }

    #[Test]
    public function it_maps_only_the_standard_regler_controller_to_the_existing_catalogue_card(): void
    {
        $method = new ReflectionMethod(SyncThermokraftCommand::class, 'isStandardReglerController');
        $method->setAccessible(true);
        $command = new SyncThermokraftCommand;

        $this->assertTrue($method->invoke($command, 'Контроллер ReglerPro Wifi'));
        $this->assertFalse($method->invoke($command, 'Комплект автоматики (контроллер ReglerPro Wifi + вентилятор VSK 120)'));
        $this->assertFalse($method->invoke($command, 'Контроллер котла REGLER GRANDA, wi-fi'));
    }

    /** @return array<int,array<string,mixed>> */
    private function parse(string $csv, string $gid, string $kind): array
    {
        $method = new ReflectionMethod(SyncThermokraftCommand::class, 'parseCsv');
        $method->setAccessible(true);

        return $method->invoke(new SyncThermokraftCommand, $csv, $gid, $kind);
    }

    #[Test]
    public function it_skips_brands_not_taken_from_thermokraft(): void
    {
        $csv = implode("
", [
            'Наименование,Артикул,Штрихкод,,Опт,РРЦ,Количество,Гарантия,Бренд,Страна,Описание',
            'ЕТ-1000,1.2004,,,900,1100,1,12,С-ТЭНК,Беларусь,',
            'Термостат Auraton 200,,,,90,120,3,24,Auraton,Польша,',
            'Котел "Каракан" 20ТЭГ-3,,,,1216,1520,0,24,СТЭН,Россия,',
        ]);

        $rows = $this->parse($csv, '1879571452', 'solid_fuel');

        $this->assertSame(['СТЭН'], array_column($rows, 'brand'));
    }

    #[Test]
    public function it_splits_a_with_and_without_automation_price_into_two_rows(): void
    {
        $csv = implode("\n", [
            'Наименование,Артикул,Штрихкод,,Опт,РРЦ,Количество,Гарантия,Бренд,Страна,Описание',
            'Котел UNIVAT Deep 20 кВт,,,,2705/3210,3260/3868,1,60,UNIVAT,Беларусь,',
        ]);

        $rows = $this->parse($csv, '2059074837', 'boiler');

        $this->assertSame(['Котел UNIVAT Deep 20 кВт без автоматики', 'Котел UNIVAT Deep 20 кВт с автоматикой'], array_column($rows, 'name'));
        $this->assertSame([2705.0, 3210.0], array_column($rows, 'wholesale'));
        $this->assertSame([3260.0, 3868.0], array_column($rows, 'retail'));
        $this->assertNotSame($rows[0]['article_key'], $rows[1]['article_key']);
    }

    #[Test]
    public function it_builds_the_same_model_key_for_sheet_rows_and_catalogue_names(): void
    {
        $command = new SyncThermokraftCommand;
        $key = new ReflectionMethod($command, 'modelKey');

        $this->assertSame(
            $key->invoke($command, 'Котел Tenko Економ 4,5_220'),
            $key->invoke($command, 'Электрический котел Tenko ЭКОНОМ (КЕ) 4,5 кВт 220В'),
        );
        $this->assertSame(
            $key->invoke($command, 'Котел Tenko Cтандарт 7,5_220'),
            $key->invoke($command, 'Электрический котел Tenko Стандарт 7.5 кВт 220В (с насосом Grundfos)'),
        );
        $this->assertNotSame(
            $key->invoke($command, 'Котел "Каракан" 20ТЭГВ-3 (двухконтурный)'),
            $key->invoke($command, 'Твердотопливный котел Каракан 20ТЭГ 3'),
        );
        $this->assertNotSame(
            $key->invoke($command, 'Котел Tenko Премиум Плюс 7,5_220'),
            $key->invoke($command, 'Электрический котел Tenko Премиум 7.5 кВт 220В (с насосом Grundfos)'),
        );
    }
}

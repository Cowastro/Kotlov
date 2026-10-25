<?php

namespace Tests\Unit;

use App\Support\Catalog\TskPumpClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TskPumpClassifierTest extends TestCase
{
    #[DataProvider('products')]
    public function test_it_classifies_only_confident_tsk_product_types(string $name, ?string $expected): void
    {
        $this->assertSame($expected, TskPumpClassifier::categorySlug($name));
    }

    public static function products(): array
    {
        return [
            ['UNIPUMP WP-40', 'poverhnostnyie'],
            ['Поверхностный насос с частотным преобразователем PSB 5-40', 'poverhnostnyie'],
            ['Насосная станция AUTO JET 100 L', 'nasosnyie-stantsii'],
            ['Скважинный насос ECO VINT 2', 'skvajinnye-nasosy'],
            ['Насос Водомет 55/50', 'skvajinnye-nasosy'],
            ['Насос дренажный WQ 8-8', 'drenajnyie'],
            ['Циркуляционный насос UPC 25-40', 'tsirkulyatsionnyie'],
            ['Гидроаккумулятор 50 л', 'membrannyie-baki'],
            ['Блок управления насосом ТУРБИПРЕСС 1,5 кВт', 'komplektuyushchie-dlya-nasosov'],
            ['UNIPUMP Рукав напорный (65мм; 50м)', 'komplektuyushchie-dlya-nasosov'],
            ['Манометр UNIPUMP (радиальный)', 'komplektuyushchie-dlya-nasosov'],
            ['UNIPUMP Рабочий винт в обойме для ECO VINT 1 D', 'komplektuyushchie-dlya-nasosov'],
            ['Ротор JET 60 S (d-55 мм, вал-14 мм)', 'komplektuyushchie-dlya-nasosov'],
            ['UNIPUMP Фильтр-грязевик Y-образный 1/2"', 'filtry'],
            ['UNIPUMP Фильтрующий элемент ВП-BB10 20М веревочный', 'filtry'],
            ['UNIPUMP AUPS 1100', 'nasosnyie-stantsii'],
            ['Циркуляционный насос с мокрым ротором UNIPUMP UPS 25-40', 'tsirkulyatsionnyie'],
            ['Грязевой насос ГНОМ 10-10', 'drenajnyie'],
            ['Консоль JY1000', null],
            ['Шланг напорный', null],
        ];
    }
}

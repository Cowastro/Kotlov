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
            ['Насосная станция UNIPUMP АКВАРОБОТ JET 100 L (r/a 2л)', 'nasosnyie-stantsii'],
            ['Гидроаккумулятор 100л гор. нерж. сталь, мембрана EPDM UNIPUMP', 'membrannyie-baki'],
            ['Мембрана 150-200 л EPDM проходная', 'komplektuyushchie-dlya-nasosov'],
            ['Скважинный многоступенчатый насос 4SDm 3/12', 'skvajinnye-nasosy'],
            ['Многоступенчатый насос Wellmix CV 1- 4-0-HQCV', 'mnogostupenchatye-nasosy'],
            ['ECO 2-73 (кабель - 40 м)', 'skvajinnye-nasosy'],
            ['Гидроаккумулятор 150л вер. с манометром UNIPUMP', 'membrannyie-baki'],
            ['Расширительный бак M1500RV UNIGB', 'membrannyie-baki'],
            ['Манометр для гидроаккумулятора UNIPUMP', 'komplektuyushchie-dlya-nasosov'],
            ['Консоль JY1000', null],
            ['Шланг напорный', null],
        ];
    }
}

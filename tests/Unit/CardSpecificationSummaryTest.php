<?php

namespace Tests\Unit;

use App\Support\Catalog\CardSpecificationSummary;
use PHPUnit\Framework\TestCase;

class CardSpecificationSummaryTest extends TestCase
{
    public function test_pump_summary_compares_one_head_flow_and_power_without_changing_the_facts(): void
    {
        $facts = ['Макс. напор' => '25 м', 'Макс. расход' => '15 м³/ч', 'Макс. всасывание' => '8 м', 'Номин. напор' => '18 м', 'Номин. расход' => '10 м³/ч', 'Мощность двигателя' => '1,5 кВт', 'Подключение DN' => '50'];
        $this->assertSame(['Макс. напор' => '25 м', 'Макс. расход' => '15 м³/ч', 'Мощность двигателя' => '1,5 кВт'], CardSpecificationSummary::select($facts));
        unset($facts['Макс. напор'],$facts['Макс. расход']);
        $this->assertSame(['Номин. напор' => '18 м', 'Номин. расход' => '10 м³/ч', 'Мощность двигателя' => '1,5 кВт'], CardSpecificationSummary::select($facts));
        $this->assertSame([], CardSpecificationSummary::select(['Данные' => 'Уточняются']));
    }

    public function test_other_categories_keep_their_existing_order_and_delta_t(): void
    {
        $facts = ['Материал' => 'Сталь', 'Подключение' => 'Нижнее', 'Теплоотдача при ΔT 70 °C' => '1200 Вт', 'Высота' => '500 мм'];
        $this->assertSame(array_slice($facts, 0, 3, true), CardSpecificationSummary::select($facts));
    }
}

<?php

namespace Tests\Unit;

use App\Support\DiameterGrid;
use PHPUnit\Framework\TestCase;

class DiameterGridTest extends TestCase
{
    public function test_names_split_into_the_kind_and_the_diameter(): void
    {
        $this->assertSame(['row' => 'Отвод моно Теплов и Сухов ОМ-Р 45*, 430, 0.8', 'diameter' => '150'],
            DiameterGrid::parse('Отвод моно Теплов и Сухов ОМ-Р 45*, 430, 0.8, Ø 150'));
        $this->assertSame(['row' => 'Отвод Теплов и Сухов ОТ-Р 45* 430, 0.8/430, 0.5', 'diameter' => '150/210'],
            DiameterGrid::parse('Отвод Теплов и Сухов ОТ-Р 45* 430, 0.8/430, 0.5, Ø 150/210'));
        $this->assertSame(['row' => 'Труба Ferrum 1 м', 'diameter' => '115'], DiameterGrid::parse('Труба Ferrum D115 1 м'));
        $this->assertNull(DiameterGrid::parse('Darco Колено универс. KNSR 150/45-CZ2 (ML) 2сек'));
        $this->assertNull(DiameterGrid::parse('Хомут Ø 150 на трубу Ø 200'));
    }

    public function test_items_of_one_kind_form_a_table_by_diameter(): void
    {
        $item = fn (int $id, string $name) => (object) ['id' => $id, 'name' => $name];
        $grid = DiameterGrid::build(collect([
            $item(1, 'Отвод ОМ-Р 45*, 430, 0.8, Ø 200'),
            $item(2, 'Отвод ОМ-Р 45*, 430, 0.8, Ø 150'),
            $item(3, 'Отвод ОМ-Р 87*, 430, 0.8, Ø 150'),
            $item(4, 'Darco Колено KNSR 150/45'),
            $item(5, 'Отвод ОМ-Р 45*, 430, 0.8, Ø 120'),
            $item(6, 'Отвод ОТ-Р 45* 430, 0.8/430, 0.5, Ø 150/210'),
            $item(7, 'Отвод ОТ-Р 45* 430, 0.8/430, 0.5, Ø 200/260'),
            $item(8, 'Тройник ТМ-Р 87*, Ø 150/210'),
        ]));

        [$mono, $pair] = $grid['tables'];
        $this->assertSame(['120', '150', '200'], $mono['columns']);
        $this->assertSame([5, 2, 1], array_map(fn ($column) => $mono['rows']['Отвод ОМ-Р 45*, 430, 0.8'][$column]->id, $mono['columns']));
        $this->assertSame(3, $mono['rows']['Отвод ОМ-Р 87*, 430, 0.8']['150']->id);
        $this->assertSame(['150/210', '200/260'], $pair['columns']);
        $this->assertSame(8, $pair['rows']['Тройник ТМ-Р 87*']['150/210']->id);
        $this->assertSame([4], array_map(fn ($p) => $p->id, $grid['rest']));
        $this->assertNull(DiameterGrid::build(collect([$item(1, 'Труба Ø 150'), $item(2, 'Зонт Ø 200')])));
    }
}

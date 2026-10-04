<?php

namespace Tests\Feature;

use Illuminate\Support\Collection;
use Tests\TestCase;

class ElectricSaunaHeaterQuickFiltersTest extends TestCase
{
    public function test_quick_filters_render_real_volume_attribute_links(): void
    {
        $filterAttributes = collect([
            $this->attribute(215, 'Максимальный объем парилки (m3)', [
                $this->option(202, 'до 5', 21),
                $this->option(203, '5 — 10', 73),
                $this->option(204, '10 — 15', 58),
                $this->option(205, '15 — 20', 31),
            ]),
        ]);

        $html = view('partials.electric-sauna-heater-quick-filters', compact('filterAttributes'))->render();

        $this->assertStringContainsString('Подберите электрокаменку по объёму парной', $html);
        $this->assertStringContainsString('15–20 м³', $html);
        $this->assertStringContainsString('attr%5B215%5D%5B0%5D=205', $html);
        $this->assertStringContainsString('catalog-products', $html);
    }

    private function attribute(int $id, string $name, array $options): object
    {
        return (object) [
            'id' => $id,
            'name' => $name,
            'options' => new Collection($options),
        ];
    }

    private function option(int $id, string $name, int $count): object
    {
        return (object) [
            'id' => $id,
            'name' => $name,
            'products_count' => $count,
        ];
    }
}

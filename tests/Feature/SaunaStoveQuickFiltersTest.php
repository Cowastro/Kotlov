<?php

namespace Tests\Feature;

use Illuminate\Support\Collection;
use Tests\TestCase;

class SaunaStoveQuickFiltersTest extends TestCase
{
    public function test_quick_filters_render_real_catalog_attribute_links(): void
    {
        $filterAttributes = collect([
            $this->attribute(209, 'Максимальный объем парилки (m3)', [
                $this->option(172, 'до 15', 94),
                $this->option(173, '15—20', 209),
            ]),
            $this->attribute(201, 'Дверца', [
                $this->option(163, 'со стеклом', 347),
            ]),
            $this->attribute(210, 'Выносная топка', [
                $this->option(175, 'да', 459),
            ]),
        ]);

        $html = view('partials.sauna-stove-quick-filters', compact('filterAttributes'))->render();

        $this->assertStringContainsString('Подберите печь для своей парной', $html);
        $this->assertStringContainsString('до 15 м³', $html);
        $this->assertStringContainsString('со стеклом', $html);
        $this->assertStringContainsString('attr%5B209%5D%5B0%5D=172', $html);
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

<?php

namespace Tests\Feature;

use Tests\TestCase;

class SolidFuelBoilerQuickFiltersTest extends TestCase
{
    public function test_quick_filters_render_real_area_and_boiler_type_links(): void
    {
        $filterAttributes = collect([
            $this->attribute(61, 'Обогреваемая площадь (m2)', [
                $this->option(1367, 'до 150 м²', 110),
                $this->option(1368, '150–300 м²', 193),
            ]),
            $this->attribute(60, 'Тип котла', [
                $this->option(70, 'на естественной тяге', 332),
                $this->option(72, 'с автоматикой', 170),
                $this->option(73, 'автоматическая подача топлива', 55),
            ]),
        ]);

        $html = view('partials.solid-fuel-boiler-quick-filters', compact('filterAttributes'))->render();

        $this->assertStringContainsString('Подберите твердотопливный котёл', $html);
        $this->assertStringContainsString('до 150 м²', $html);
        $this->assertStringContainsString('ручная загрузка', $html);
        $this->assertStringContainsString('автоподача', $html);
        $this->assertStringContainsString('attr%5B61%5D%5B0%5D=1367', $html);
        $this->assertStringContainsString('attr%5B60%5D%5B0%5D=73', $html);
        $this->assertStringContainsString('332', $html);
    }

    public function test_active_quick_filter_can_be_removed_without_losing_other_query_parameters(): void
    {
        request()->merge([
            'attr' => [61 => [1367]],
            'brand' => 15,
            'page' => 3,
        ]);

        $filterAttributes = collect([
            $this->attribute(61, 'Обогреваемая площадь (m2)', [
                $this->option(1367, 'до 150 м²', 110),
            ]),
        ]);

        $html = view('partials.solid-fuel-boiler-quick-filters', compact('filterAttributes'))->render();

        $this->assertStringContainsString('aria-current="true"', $html);
        $this->assertStringContainsString('brand=15', $html);
        $this->assertStringNotContainsString('page=3', $html);
        $this->assertStringNotContainsString('attr%5B61%5D', $html);
    }

    private function attribute(int $id, string $name, array $options): object
    {
        return (object) [
            'id' => $id,
            'name' => $name,
            'options' => collect($options),
        ];
    }

    private function option(int $id, string $name, int $productsCount): object
    {
        return (object) [
            'id' => $id,
            'name' => $name,
            'products_count' => $productsCount,
        ];
    }
}

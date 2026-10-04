<?php

namespace Tests\Feature;

use Tests\TestCase;

class GasAndElectricBoilerQuickFiltersTest extends TestCase
{
    public function test_gas_boiler_guide_uses_real_filter_options_and_internal_links(): void
    {
        $filterAttributes = collect([
            $this->attribute(53, 'Тип', [
                $this->option(52, 'одноконтурный', 86),
                $this->option(53, 'двухконтурный', 97),
                $this->option(722, 'конденсационный', 63),
            ]),
            $this->attribute(54, 'Камера сгорания', [
                $this->option(55, 'закрытая', 156),
            ]),
            $this->attribute(55, 'Обогреваемая площадь (m2)', [
                $this->option(1358, 'до 150 м²', 49),
            ]),
        ]);

        $html = view('partials.gas-boiler-quick-filters', compact('filterAttributes'))->render();

        $this->assertStringContainsString('Как выбрать газовый котёл', $html);
        $this->assertStringContainsString('двухконтурные', $html);
        $this->assertStringContainsString('attr%5B53%5D%5B0%5D=53', $html);
        $this->assertStringContainsString('закрытая камера', $html);
        $this->assertStringContainsString('/elektricheskie', $html);
        $this->assertStringContainsString('/tverdotoplivnye', $html);
    }

    public function test_electric_boiler_guide_keeps_other_parameters_when_filtering(): void
    {
        request()->merge(['brand' => 15, 'page' => 2]);
        $filterAttributes = collect([
            $this->attribute(65, 'Обогреваемая площадь (m2)', [
                $this->option(1375, 'до 90 м²', 181),
                $this->option(1376, '90–180 м²', 136),
                $this->option(1377, 'свыше 180 м²', 116),
            ]),
        ]);

        $html = view('partials.electric-boiler-quick-filters', compact('filterAttributes'))->render();

        $this->assertStringContainsString('Как выбрать электрический котёл', $html);
        $this->assertStringContainsString('до 90 м²', $html);
        $this->assertStringContainsString('attr%5B65%5D%5B0%5D=1375', $html);
        $this->assertStringContainsString('brand=15', $html);
        $this->assertStringNotContainsString('page=2', $html);
        $this->assertStringContainsString('/teplovyie-nasosyi', $html);
    }

    public function test_guides_render_even_when_filter_data_is_missing(): void
    {
        $filterAttributes = collect();

        $gasHtml = view('partials.gas-boiler-quick-filters', compact('filterAttributes'))->render();
        $electricHtml = view('partials.electric-boiler-quick-filters', compact('filterAttributes'))->render();

        $this->assertStringContainsString('Как выбрать газовый котёл', $gasHtml);
        $this->assertStringContainsString('Как выбрать электрический котёл', $electricHtml);
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

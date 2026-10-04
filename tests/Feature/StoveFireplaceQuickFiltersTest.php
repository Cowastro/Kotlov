<?php

namespace Tests\Feature;

use App\Models\Category;
use Tests\TestCase;

class StoveFireplaceQuickFiltersTest extends TestCase
{
    public function test_stove_filters_render_material_and_heating_area_links(): void
    {
        $category = new Category(['slug' => 'pechki']);
        $filterAttributes = collect([
            $this->attribute(791, 'Материал', [
                $this->option(1, 'Чугун', 299),
                $this->option(2, 'Сталь', 233),
            ]),
            $this->attribute(944, 'Площадь отапливаемого помещения', [
                $this->option(3, 'Менее 50 м2', 46),
                $this->option(4, '50 м2 - 100 м2', 319),
                $this->option(5, 'Более 100 м2', 162),
            ]),
        ]);

        $html = view('partials.stove-fireplace-quick-filters', compact('category', 'filterAttributes'))->render();

        $this->assertStringContainsString('Подберите печь для дома', $html);
        $this->assertStringContainsString('чугун', $html);
        $this->assertStringContainsString('сталь', $html);
        $this->assertStringContainsString('50–100 м²', $html);
        $this->assertStringContainsString('attr%5B791%5D%5B0%5D=1', $html);
        $this->assertStringContainsString('299', $html);
        $this->assertStringContainsString('Для постоянного отопления', $html);
        $this->assertStringContainsString('/dymohody', $html);
    }

    public function test_fireplace_filters_match_dash_variants_in_power_options(): void
    {
        $category = new Category(['slug' => 'topki']);
        $filterAttributes = collect([
            $this->attribute(839, 'Материал', [
                $this->option(6, 'чугун', 176),
                $this->option(7, 'сталь', 147),
            ]),
            $this->attribute(836, 'Мощность', [
                $this->option(8, 'до 10 кВт', 34),
                $this->option(9, '10 — 15 кВт', 162),
                $this->option(10, '15 — 20 кВт', 115),
            ]),
        ]);

        $html = view('partials.stove-fireplace-quick-filters', compact('category', 'filterAttributes'))->render();

        $this->assertStringContainsString('Подберите каминную топку', $html);
        $this->assertStringContainsString('10–15 кВт', $html);
        $this->assertStringContainsString('attr%5B836%5D%5B0%5D=9', $html);
    }

    public function test_active_filter_link_removes_it_and_keeps_brand(): void
    {
        request()->merge([
            'attr' => [791 => [1]],
            'brand' => 45,
            'page' => 2,
        ]);
        $category = new Category(['slug' => 'pechi-kaminy']);
        $filterAttributes = collect([
            $this->attribute(791, 'Материал', [
                $this->option(1, 'Чугун', 299),
            ]),
        ]);

        $html = view('partials.stove-fireplace-quick-filters', compact('category', 'filterAttributes'))->render();

        $this->assertStringContainsString('aria-current="true"', $html);
        $this->assertStringContainsString('brand=45', $html);
        $this->assertStringNotContainsString('page=2', $html);
        $this->assertStringNotContainsString('attr%5B791%5D', $html);
    }

    public function test_heating_stove_filters_use_available_material_and_area_facts(): void
    {
        $category = new Category(['slug' => 'peci-drovianye-otopitelnye']);
        $filterAttributes = collect([
            $this->attribute(791, 'Материал', [
                $this->option(1, 'Чугун', 2),
                $this->option(2, 'Сталь', 37),
            ]),
            $this->attribute(944, 'Площадь отапливаемого помещения', [
                $this->option(3, '50 м2 - 100 м2', 14),
            ]),
        ]);

        $html = view('partials.stove-fireplace-quick-filters', compact('category', 'filterAttributes'))->render();

        $this->assertStringContainsString('Подберите отопительную печь', $html);
        $this->assertStringContainsString('чугун', $html);
        $this->assertStringContainsString('сталь', $html);
        $this->assertStringContainsString('50–100 м²', $html);
        $this->assertStringContainsString('Площадь в характеристиках служит ориентиром', $html);
        $this->assertStringContainsString('/montazh-kaminov', $html);
    }

    public function test_heating_stove_filters_keep_room_volume_separate_from_area(): void
    {
        $category = new Category(['slug' => 'peci-drovianye-otopitelnye']);
        $filterAttributes = collect([
            $this->attribute(944, 'Площадь отапливаемого помещения', [
                $this->option(3, '50 м2 - 100 м2', 14),
            ]),
            $this->attribute(945, 'Объём отапливаемого помещения', [
                $this->option(4, 'До 100 м3', 3),
                $this->option(5, '101 м3 - 200 м3', 11),
                $this->option(6, 'Более 200 м3', 2),
            ]),
        ]);

        $html = view('partials.stove-fireplace-quick-filters', compact('category', 'filterAttributes'))->render();

        $this->assertStringContainsString('50–100 м²', $html);
        $this->assertStringContainsString('до 100 м³', $html);
        $this->assertStringContainsString('101–200 м³', $html);
        $this->assertStringContainsString('Объём', $html);
        $this->assertStringContainsString('attr%5B945%5D%5B0%5D=5', $html);
    }

    public function test_root_stove_page_adapts_quick_filter_heading_to_selected_subcategory(): void
    {
        request()->merge(['subcategory' => 'burzhuiki-pechi']);
        $category = new Category(['slug' => 'pechki']);
        $filterAttributes = collect([
            $this->attribute(791, 'Материал', [
                $this->option(2, 'Сталь', 35),
            ]),
        ]);

        $html = view('partials.stove-fireplace-quick-filters', compact('category', 'filterAttributes'))->render();

        $this->assertStringContainsString('Подберите отопительную печь', $html);
        $this->assertStringContainsString('35', $html);
    }

    public function test_stove_selection_guide_remains_visible_when_attributes_are_not_filled(): void
    {
        $category = new Category(['slug' => 'pechki']);
        $filterAttributes = collect();

        $html = view('partials.stove-fireplace-quick-filters', compact('category', 'filterAttributes'))->render();

        $this->assertStringContainsString('Подберите печь для дома', $html);
        $this->assertStringContainsString('Для постоянного отопления', $html);
        $this->assertStringNotContainsString('Быстрый выбор:', $html);
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

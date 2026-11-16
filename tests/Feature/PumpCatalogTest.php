<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Support\Catalog\PumpCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PumpCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function pump(string $slug, string $name, array $specs = []): Product
    {
        $root = Category::firstOrCreate(['slug' => PumpCatalog::ROOT], ['name' => 'Насосы и водоснабжение', 'parent_id' => 0, 'is_active' => true]);
        $parent = in_array($slug, ['mnogostupenchatye-nasosy', 'in-lain-nasosy'], true)
            ? Category::firstOrCreate(['slug' => 'promyshlennye-nasosy'], ['name' => 'Промышленные насосы', 'parent_id' => $root->id, 'is_active' => true]) : $root;
        $category = Category::firstOrCreate(['slug' => $slug], ['name' => PumpCatalog::NAMES[$slug] ?? 'Комплектующие', 'parent_id' => $parent->id, 'is_active' => true]);
        return Product::create(['category_id' => $category->id, 'name' => $name, 'slug' => 'pump-'.Product::count(), 'sku' => 'PUMP-'.Product::count(), 'price' => 100, 'is_active' => true, 'is_archived' => false, 'specs' => $specs]);
    }

    public function test_task_navigation_separates_household_industrial_and_accessory_destinations(): void
    {
        $this->pump('tsirkulyatsionnyie', 'Циркуляционный насос');
        $this->pump('skvajinnye-nasosy', 'Скважинный насос');
        $this->pump('mnogostupenchatye-nasosy', 'Многоступенчатый насос');
        $this->pump('komplektuyushchie-dlya-nasosov', 'Комплектующее');
        $this->get('https://kotlov.by/vodosnabzhenie')->assertOk()->assertSee('Для какой задачи нужен насос?')
            ->assertSee('Для отопления')->assertSee('Вода из скважины')->assertSee('Промышленные системы')
            ->assertSee('href="https://kotlov.by/komplektuyushchie-dlya-nasosov"', false);
        $this->get('https://kotlov.by/promyshlennye-nasosy')->assertOk()->assertSee('Многоступенчатые');
        $this->get('https://kotlov.by/skvajinnye-nasosy')->assertOk()->assertDontSee('Быстрый подбор насоса');
    }

    public function test_head_flow_and_voltage_combine_with_search_price_and_existing_urls(): void
    {
        $match = $this->pump('mnogostupenchatye-nasosy', 'Wilo Test 40', ['Максимальный напор, м' => '50', 'Максимальный расход, м³/ч' => '4', 'Напряжение, В' => '230']);
        $this->pump('mnogostupenchatye-nasosy', 'Wilo Small', ['Максимальный напор, м' => '10', 'Максимальный расход, м³/ч' => '8']);
        $this->pump('mnogostupenchatye-nasosy', 'Wilo Unknown', ['Максимальный напор, м' => '100', 'Максимальная производительность' => '400']);
        $this->get('https://kotlov.by/mnogostupenchatye-nasosy?pump_head=40&pump_flow=3,5&pump[voltage]=220&q=Wilo&price_max=120')
            ->assertOk()->assertViewHas('products', fn ($products) => $products->modelKeys() === [$match->id])
            ->assertSee('Макс. напор от 40 м')->assertSee('Макс. расход от 3,5 м³/ч')
            ->assertSee('/mnogostupenchatye-nasosy/'.$match->slug, false)
            ->assertSee('noindex, follow')->assertSee('<link rel="canonical" href="https://kotlov.by/mnogostupenchatye-nasosy">', false);
    }

    public function test_units_are_converted_but_missing_conflicting_and_nominal_data_are_not_maxima(): void
    {
        $catalog = new PumpCatalog;
        foreach ([
            [['Максимальный расход, л/мин' => '100'], ['flow' => 6.0]],
            [['Максимальная производительность' => '3,5 м³/ч (58,3 л/мин)'], ['flow' => 3.5]],
            [['Максимальный расход, л/ч' => '3500'], ['flow' => 3.5]],
            [['Максимальная производительность' => '100'], []],
            [['Номинальная подача, м³/ч' => '4', 'Номинальный напор, м' => '20'], []],
            [['Максимальный напор, м' => '40', 'Макс. напор, м' => '20'], []],
            [['Максимальный напор, м' => '4–6'], []],
            [['Монтажная длина, мм' => '180', 'Длина, мм' => '350'], ['length' => 180.0]],
            [['Диаметр насоса, мм (дюймов)' => '74 (3")'], ['diameter' => 74.0]],
            [['Диаметр насоса' => "3\" (76 мм)"], ['diameter' => 76.2]],
        ] as [$specs, $expected]) {
            $facts = $catalog->productFacts(new Product(['name' => 'Насос 25-60 180', 'specs' => $specs]), 'tsirkulyatsionnyie', $catalog->dimensions('tsirkulyatsionnyie'));
            $this->assertSame($expected, $facts['numbers']);
        }
    }

    public function test_attributes_supply_explicit_units_and_converted_equivalents_agree(): void
    {
        $p = $this->pump('tsirkulyatsionnyie', 'Насос с атрибутами', ['Максимальный расход, м³/ч' => '6']);
        foreach ([['Максимальный напор', 'м', '6'], ['Максимальная производительность', 'л/мин', '100'], ['Монтажная длина', 'мм', '180']] as [$name, $suffix, $value]) {
            $attribute = Attribute::create(['name' => $name, 'suffix' => $suffix, 'type' => 'value', 'in_filter' => true, 'category_id' => $p->category_id]);
            ProductAttributeValue::create(['attribute_id' => $attribute->id, 'product_id' => $p->id, 'value' => $value]);
        }
        $this->get('https://kotlov.by/tsirkulyatsionnyie?pump_head=6&pump_flow=6&pump[length]=180')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$p->id])->assertSee('6 м³/ч');
    }

    public function test_voltage_does_not_treat_three_phase_230_as_household_single_phase(): void
    {
        $catalog = new PumpCatalog;
        foreach ([['1~230 V', '220'], ['3~230 V', 'three220'], ['3~230/400 V', '380'], ['380', '380']] as [$value, $option]) {
            $f = $catalog->productFacts(new Product(['specs' => ['Параметры электросети' => $value]]), 'mnogostupenchatye-nasosy', $catalog->dimensions('mnogostupenchatye-nasosy'));
            $this->assertSame([$option], $f['options']['voltage']);
        }
    }

    public function test_legacy_upc_head_outliers_are_not_used_as_real_capacity(): void
    {
        $p = $this->pump('tsirkulyatsionnyie', 'Насос Unipump UPC 32-80 180', ['Максимальный напор, м' => 76]);
        $this->get('https://kotlov.by/tsirkulyatsionnyie')->assertOk()->assertSee('Требует проверки');
        $this->get('https://kotlov.by/tsirkulyatsionnyie?pump_head=40')->assertOk()
            ->assertViewHas('products', fn ($products) => ! $products->contains('id', $p->id));
        $facts = (new PumpCatalog)->productFacts(new Product(['name' => 'UNIPUMP UPC 32-80 180', 'specs' => ['Максимальный напор, м' => 7.6]]), 'tsirkulyatsionnyie', []);
        $this->assertSame(7.6, $facts['numbers']['head']);
    }

    public function test_brand_choices_and_empty_results_follow_selected_pump_parameters(): void
    {
        $first = $this->pump('tsirkulyatsionnyie', 'Большой насос', ['Максимальный напор, м' => 10]);
        $second = $this->pump('tsirkulyatsionnyie', 'Малый насос', ['Максимальный напор, м' => 4]);
        $a = Brand::create(['name' => 'Large', 'slug' => 'large']); $b = Brand::create(['name' => 'Small', 'slug' => 'small']);
        $first->update(['brand_id' => $a->id]); $second->update(['brand_id' => $b->id]);
        $this->get('https://kotlov.by/tsirkulyatsionnyie?pump_head=8')->assertOk()->assertViewHas('brands', fn ($brands) => $brands->modelKeys() === [$a->id]);
        $this->get('https://kotlov.by/tsirkulyatsionnyie?pump_head=1000')->assertOk()->assertViewHas('products', fn ($products) => $products->total() === 0);
        $this->get('https://kotlov.by/tsirkulyatsionnyie?pump_head[]=8&pump[voltage][]=220')->assertOk();
        $this->get('https://kotlov.by/tsirkulyatsionnyie?pump_flow=-10&pump_head=bad')->assertOk();
    }
}

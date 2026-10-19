<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncBoilerRangeFiltersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_repairs_explicit_ranges_and_never_invents_area_from_power(): void
    {
        $category = Category::query()->create([
            'name' => 'Твердотопливные котлы',
            'slug' => 'tverdotoplivnye',
            'is_active' => true,
        ]);
        $areaFilter = $this->filter($category, 'Обогреваемая площадь (m2)', [
            'до 150 м²', '150–300 м²', '300–500 м²', 'свыше 500 м²',
        ]);
        $powerFilter = $this->filter($category, 'Мощность (кВт)', [
            'до 20 кВт', '20–50 кВт', '50–100 кВт', '100–200 кВт', 'свыше 200 кВт',
        ]);

        $universal = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Котёл Sakovich Universal 35 кВт',
            'slug' => 'universal-35-filter-test',
            'sku' => 'FILTER-UNIVERSAL-35',
            'price' => 4700,
            'is_active' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'specs' => [
                ['key' => 'Площадь отопления', 'value' => '350 м²'],
                ['key' => 'Номинальная мощность', 'value' => '35 кВт'],
            ],
        ]);
        ProductAttributeValue::query()->create([
            'product_id' => $universal->id,
            'attribute_id' => $areaFilter->id,
            'option_id' => $areaFilter->options->firstWhere('name', 'до 150 м²')->id,
            'value' => '',
        ]);

        $powerOnly = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Котёл тестовый 25 кВт',
            'slug' => 'power-only-filter-test',
            'sku' => 'FILTER-POWER-ONLY',
            'price' => 1000,
            'is_active' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $boundary = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Котёл с характеристиками старого формата',
            'slug' => 'boundary-filter-test',
            'sku' => 'FILTER-BOUNDARY',
            'price' => 1500,
            'is_active' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'specs' => [
                'Отапливаемая площадь' => '300 м²',
                'Мощность котла' => '20 кВт',
            ],
        ]);

        $this->artisan('catalog:sync-boiler-range-filters', ['--apply' => true, '--sample' => 0])
            ->assertSuccessful();

        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $universal->id,
            'attribute_id' => $areaFilter->id,
            'option_id' => $areaFilter->options->firstWhere('name', '300–500 м²')->id,
        ]);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $universal->id,
            'attribute_id' => $powerFilter->id,
            'option_id' => $powerFilter->options->firstWhere('name', '20–50 кВт')->id,
        ]);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $powerOnly->id,
            'attribute_id' => $powerFilter->id,
            'option_id' => $powerFilter->options->firstWhere('name', '20–50 кВт')->id,
        ]);
        $this->assertDatabaseMissing('product_attribute_values', [
            'product_id' => $powerOnly->id,
            'attribute_id' => $areaFilter->id,
        ]);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $boundary->id,
            'attribute_id' => $areaFilter->id,
            'option_id' => $areaFilter->options->firstWhere('name', '150–300 м²')->id,
        ]);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $boundary->id,
            'attribute_id' => $powerFilter->id,
            'option_id' => $powerFilter->options->firstWhere('name', 'до 20 кВт')->id,
        ]);
    }

    private function filter(Category $category, string $name, array $labels): Attribute
    {
        $attribute = Attribute::query()->create([
            'category_id' => $category->id,
            'group_id' => 0,
            'sort_order' => 1,
            'type' => 'select',
            'name' => $name,
            'in_filter' => true,
            'in_product' => true,
        ]);
        foreach ($labels as $index => $label) {
            AttributeOption::query()->create([
                'attribute_id' => $attribute->id,
                'name' => $label,
                'sort_order' => $index + 1,
            ]);
        }
        $attribute->load('options');

        return $attribute;
    }
}

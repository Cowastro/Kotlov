<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TisStandard50PriceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_list_groups_products_by_system_element_and_diameter(): void
    {
        Category::query()->updateOrCreate(['slug' => 'dymohody'], ['name' => 'Дымоходы', 'parent_id' => 0, 'is_active' => true]);
        $mono = Category::query()->updateOrCreate(['slug' => 'truby-mono'], ['name' => 'Трубы Моно', 'parent_id' => 0, 'is_active' => true]);
        $thermo = Category::query()->updateOrCreate(['slug' => 'truby-sendvich'], ['name' => 'Трубы Термо', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-test', 'is_active' => true]);

        $this->product($mono, $brand, 'Моно', '115', 98.55, 1);
        $this->product($mono, $brand, 'Моно', '150', 128.23, 2);
        $this->product($thermo, $brand, 'Термо', '115/210', 242.66, 3);

        $this->get(route('chimneys.tis-standard-50'))
            ->assertOk()
            ->assertSeeText('Система ТиС «Стандарт 50» Моно')
            ->assertSeeText('Система ТиС «Стандарт 50» Термо — изоляция 50 мм')
            ->assertSeeText('Ø 115 мм')
            ->assertSeeText('Ø 115/210 мм')
            ->assertSeeText('98,55 BYN')
            ->assertSee('/truby-mono/tis50-test-1', false);

        $this->get(route('chimneys.tis-standard-50.csv'))
            ->assertOk()
            ->assertDownload('tis-standart-50-kotlov-by.csv');
    }

    public function test_official_snapshot_contains_both_complete_price_tables(): void
    {
        $data = json_decode((string) file_get_contents(resource_path('data/catalog/tis-standard-50.json')), true);

        $this->assertSame('RUB', $data['source_currency']);
        $this->assertCount(106, $data['series']['mono']['items']);
        $this->assertCount(97, $data['series']['thermo']['items']);
        $this->assertNotEmpty($data['series']['mono']['items'][0]['source_url']);
        $this->assertGreaterThan(0, $data['series']['thermo']['items'][0]['price_rub']);
        $this->assertSame(203, Product::query()->where('sku', 'like', 'TIS50-%')->count());
        $this->assertSame(203, DB::table('supplier_products')->where('supplier_article', 'like', 'TIS50-%')->where('delivery_days', 7)->count());
    }

    private function product(Category $category, Brand $brand, string $execution, string $diameter, float $price, int $number): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Тестовая труба '.$diameter,
            'slug' => 'tis50-test-'.$number,
            'sku' => 'TIS50-TEST-'.$number,
            'price' => $price,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'sort_order' => $number,
            'specs' => [
                'Серия' => 'ТиС «Стандарт 50»',
                'Исполнение' => $execution,
                'Тип элемента' => 'Труба L1000',
                'Диаметр' => $diameter,
            ],
        ]);
    }
}

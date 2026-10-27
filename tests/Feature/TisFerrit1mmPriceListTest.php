<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TisFerrit1mmPriceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_list_groups_products_by_system_element_and_diameter(): void
    {
        Category::query()->updateOrCreate(['slug' => 'dymohody'], ['name' => 'Дымоходы', 'parent_id' => 0, 'is_active' => true]);
        $mono = Category::query()->updateOrCreate(['slug' => 'truby-mono'], ['name' => 'Трубы Моно', 'parent_id' => 0, 'is_active' => true]);
        $thermo = Category::query()->updateOrCreate(['slug' => 'truby-sendvich'], ['name' => 'Трубы Термо', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-test', 'is_active' => true]);

        $this->product($mono, $brand, 'Моно', '80', 98.55, 1);
        $this->product($mono, $brand, 'Моно', '120', 128.23, 2);
        $this->product($thermo, $brand, 'Термо', '80/140', 242.66, 3);

        $this->get(route('chimneys.tis-ferrit-1mm'))
            ->assertOk()
            ->assertSeeText('Система ТиС «Феррит 1 мм» Моно')
            ->assertSeeText('Система ТиС «Феррит 1 мм» Термо — керамоволокно')
            ->assertSeeText('Ø 80 мм')
            ->assertSeeText('Ø 80/140 мм')
            ->assertSeeText('98,55 BYN')
            ->assertSee('/truby-mono/tisf1-test-1', false);

        $this->get(route('chimneys.tis-ferrit-1mm.csv'))
            ->assertOk()
            ->assertDownload('tis-ferrit-1-mm-kotlov-by.csv');
    }

    public function test_official_snapshot_contains_both_complete_price_tables(): void
    {
        $data = json_decode((string) file_get_contents(resource_path('data/catalog/tis-ferrit-1mm.json')), true);

        $this->assertSame('RUB', $data['source_currency']);
        $this->assertCount(9, $data['series']['mono']['items']);
        $this->assertCount(8, $data['series']['thermo']['items']);
        $this->assertNotEmpty($data['series']['mono']['items'][0]['source_url']);
        $this->assertGreaterThan(0, $data['series']['thermo']['items'][0]['price_rub']);
    }

    private function product(Category $category, Brand $brand, string $execution, string $diameter, float $price, int $number): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Тестовая труба '.$diameter,
            'slug' => 'tisf1-test-'.$number,
            'sku' => 'TISF1-TEST-'.$number,
            'price' => $price,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'sort_order' => $number,
            'specs' => [
                'Серия' => 'ТиС «Феррит 1 мм»',
                'Исполнение' => $execution,
                'Тип элемента' => 'Труба L1000',
                'Диаметр' => $diameter,
            ],
        ]);
    }
}

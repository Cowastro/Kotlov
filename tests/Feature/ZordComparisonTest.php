<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZordComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_links_each_model_to_its_card_with_its_own_price_and_stock(): void
    {
        $category = Category::updateOrCreate(['slug' => 'signalizatory-zagazovannosti'], ['name' => 'Сигнализаторы загазованности', 'is_active' => true]);
        $supplier = Supplier::create(['code' => 'zord-test', 'name' => 'Склад', 'is_active' => true]);
        $models = [
            ['PS-008.168', 'ЗОРД УГС-03', 'signalizator-zagazovannosti-zord-ugs-03', 89.42, 1],
            ['PS-011.094', 'ЗОРД УГС-02', 'signalizator-zagazovannosti', 85, 8],
            ['KOTLOV-004901', 'GKB CO777', 'gkb-co777', 67.20, 9],
            ['KOTLOV-004900', 'GKB CO888', 'gkb-co888', 84, 7],
            ['PS-008.160', 'ЗОРД УГС-01', 'zord-ugs-01', 40, 7],
            ['KOTLOV-004899', 'GKB CO999', 'gkb-co999', 44.40, 10],
        ];
        foreach ($models as [$sku, $name, $slug, $price, $stock]) {
            $product = Product::create(['category_id' => $category->id, 'sku' => $sku, 'name' => $name, 'slug' => $slug,
                'price' => $price, 'in_stock' => true, 'is_active' => true]);
            $product->supplierProducts()->create(['supplier_id' => $supplier->id, 'supplier_article' => $sku,
                'price_byn' => $price, 'stock_quantity' => $stock, 'in_stock' => true, 'last_stock_synced_at' => now()]);
        }

        $url = 'https://kotlov.by/'.$category->slug;
        $page = $this->get($url)->assertOk();
        $page->assertDontSee('id="zord-heading"', false)
            ->assertSeeInOrder(['id="gridLayout"', 'Угарный газ (CO) и горючие газы', '85.00 BYN',
                'Природный и сжиженный газ', '89.42 BYN', 'GKB CO777', 'Дым и угарный газ (CO)',
                'GKB CO888', 'Угарный газ (CO) и метан', 'ЗОРД УГС-01', 'GKB CO999'], false)
            ->assertSee('В наличии: 8 шт.')->assertSee('В наличии: 1 шт.');
        foreach ($models as [$sku, $name, $slug]) {
            $this->get($url.'/'.$slug)->assertOk()->assertSee($sku)->assertSee($name);
        }
        $this->get($url.'?sort=price_asc')->assertOk()->assertSeeInOrder(['id="gridLayout"', 'ЗОРД УГС-01', 'GKB CO999', 'GKB CO777'], false);
    }
}

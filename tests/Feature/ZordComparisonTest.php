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
        ];
        foreach ($models as [$sku, $name, $slug, $price, $stock]) {
            $product = Product::create(['category_id' => $category->id, 'sku' => $sku, 'name' => $name, 'slug' => $slug,
                'price' => $price, 'in_stock' => true, 'is_active' => true]);
            $product->supplierProducts()->create(['supplier_id' => $supplier->id, 'supplier_article' => $sku,
                'price_byn' => $price, 'stock_quantity' => $stock, 'in_stock' => true, 'last_stock_synced_at' => now()]);
        }

        $url = 'https://kotlov.by/'.$category->slug;
        $page = $this->get($url)->assertOk();
        $page->assertSeeInOrder(['id="zord-heading"', 'href="'.$url.'/signalizator-zagazovannosti"', 'ЗОРД УГС-02',
            'Угарный газ (CO) и горючие газы', '85,00 BYN', 'В наличии: 8 шт.',
            'href="'.$url.'/signalizator-zagazovannosti-zord-ugs-03"', 'ЗОРД УГС-03',
            'Природный и сжиженный газ', '89,42 BYN', 'В наличии: 1 шт.'], false);
        foreach ($models as [$sku, $name, $slug]) {
            $this->get($url.'/'.$slug)->assertOk()->assertSee($sku)->assertSee($name);
        }
        $this->get($url.'?price_max=86')->assertOk()->assertDontSee('id="zord-heading"', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BufferTankShowcaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_groups_current_offers_by_volume_in_the_requested_order(): void
    {
        $category = Category::updateOrCreate(['slug' => 'bufernye-emkosti'], ['name' => 'Буферные ёмкости', 'is_active' => true]);
        $supplier = Supplier::create(['code' => 'buffer-test', 'name' => 'Склад', 'is_active' => true]);
        $models = [
            ['bufernaia-emkost-galmet-bufor-sgb-300-skay-fl-seryi-70-300000', 'Galmet BUFOR SG(B) 300 SKAY FL', 2343.97],
            ['bufernaya-emkost-s-tank-et-500', 'S-TANK ET-500', 1951.20],
            ['bufernaia-emkost-aquastic-aq-pt-500-erp', 'Aquastic AQ PT 500 ErP', 1345.50],
            ['galmet-bufor-sg-500', 'Galmet BUFOR SG 500', 2510.93],
        ];
        foreach ($models as [$slug, $name, $price]) {
            $product = Product::create(['category_id' => $category->id, 'name' => $name, 'slug' => $slug, 'sku' => $slug,
                'availability_status' => Product::AVAILABILITY_CHECK, 'price' => $price, 'in_stock' => str_starts_with($name, 'Galmet'), 'is_active' => true]);
            $stock = str_starts_with($name, 'Galmet') ? 2 : 0;
            $product->supplierProducts()->create(['supplier_id' => $supplier->id, 'supplier_article' => $slug,
                'price_byn' => $price, 'stock_quantity' => $stock, 'in_stock' => $stock > 0, 'last_stock_synced_at' => now()]);
        }

        $url = 'https://kotlov.by/bufernye-emkosti';
        $page = $this->get($url)->assertOk();
        $page->assertSeeInOrder(['<h1>Буферные ёмкости для отопления в Минске</h1>', 'id="buffer-group-0"',
            'Galmet BUFOR SG 500', '2 510,93 BYN', 'В наличии: 2 шт.', 'Aquastic AQ PT 500 ErP', '1 345,50 BYN', 'Доступно к заказу',
            'S-TANK ET-500', '1 951,20 BYN', 'id="buffer-group-1"', 'Galmet BUFOR SG(B) 300 SKAY FL', '2 343,97 BYN'], false);
        $this->get($url.'?in_stock=1')->assertOk()->assertDontSee('id="buffer-group-0"', false);
    }
}

<?php

namespace Tests\Feature;

use App\Console\Commands\SyncBelomoPriceListCommand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class SyncBelomoPriceListCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_price_refresh_preserves_sanbusinessgroup_stock_from_warehouse_feed(): void
    {
        $supplier = Supplier::firstOrCreate(
            ['code' => 'sanbusinessgroup'],
            ['name' => 'ООО «СанБизнесГруп»', 'currency' => 'BYN', 'currency_rate' => 1, 'is_active' => true],
        );
        $category = Category::create([
            'name' => 'Тестовые счётчики',
            'slug' => 'test-belomo-price-refresh',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Счётчик КАТА G4-3',
            'slug' => 'test-kata-g4-3',
            'sku' => 'KOTLOV-TEST-KATA',
            'price' => 470.88,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $syncId = DB::table('supplier_syncs')->insertGetId([
            'key' => 'test_belomo_stock_preservation',
            'supplier_id' => $supplier->id,
            'name' => 'ООО «СанБизнесГруп»',
            'code' => 'sanbusinessgroup',
            'title' => 'Тест закупочного прайса БелОМО',
            'command' => 'supplier:sync-belomo-pricelist',
        ]);
        $offer = SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'supplier_article' => '8348-09',
            'supplier_name' => $product->name,
            'price' => 300,
            'price_byn' => 300,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'in_stock' => true,
            'stock_quantity' => 5,
            'stock_status' => 'in_stock',
            'stock_text' => 'В наличии',
            'warehouse_name' => 'Склад Минск',
            'last_stock_synced_at' => now(),
            'match_status' => 'matched',
        ]);

        $method = new ReflectionMethod(SyncBelomoPriceListCommand::class, 'upsertSupplierProduct');
        $method->invoke(new SyncBelomoPriceListCommand, $supplier->id, $syncId, $product, [
            'article' => '8348-09',
            'name' => 'Счетчик газа ультразвуковой КАТА-G4 В-3',
            'partner_price_byn' => 339.00,
        ], now());

        $offer->refresh();
        $this->assertSame('339.00', $offer->price_byn);
        $this->assertTrue($offer->in_stock);
        $this->assertSame(5, $offer->stock_quantity);
        $this->assertSame('in_stock', $offer->stock_status);
        $this->assertSame('В наличии', $offer->stock_text);
        $this->assertSame('ООО «СанБизнесГруп»', $offer->warehouse_name);
    }
}

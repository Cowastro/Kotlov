<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RnProfiStockImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_unusable_prices_are_reported_as_failure_without_refreshing_stock(): void
    {
        $count = SupplierProduct::count();
        $path = storage_path('app/rn-empty-test-'.bin2hex(random_bytes(5)).'.csv');
        file_put_contents($path, "Наименование;ОПТ;РРЦ\nПолотенцесушитель;Ошибка;Ошибка\n");
        try {
            $this->artisan('supplier:sync-rn-profi', ['--price-file' => $path, '--apply' => true])
                ->expectsOutputToContain('contains no usable product rows')->assertFailed();
            $this->assertSame($count, SupplierProduct::count());
        } finally {
            unlink($path);
        }
    }

    public function test_package_only_csv_clears_fake_stock_without_repricing_or_relinking(): void
    {
        Http::preventStrayRequests();
        $supplier = Supplier::firstOrCreate(['code' => 'rn-profi'], ['name' => 'RN-Profi', 'is_active' => true]);
        $brand = Brand::firstOrCreate(['slug' => 'varmega'], ['name' => 'Varmega']);
        $category = Category::firstOrCreate(['slug' => 'rn-stock-test'], ['name' => 'Арматура', 'parent_id' => 0, 'is_active' => true]);
        $product = Product::create(['name' => 'Клапан VARMEGA', 'slug' => 'rn-stock-test-product', 'sku' => 'RN-STOCK-TEST', 'category_id' => $category->id, 'brand_id' => $brand->id, 'price' => 15, 'is_active' => true, 'is_archived' => false]);
        $offer = SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => 'VM04001', 'supplier_article_normalized' => 'VM04001', 'product_sku' => $product->sku, 'price_byn' => 12.5, 'in_stock' => true, 'stock_quantity' => 10, 'stock_status' => 'in_stock', 'match_status' => 'matched', 'last_stock_synced_at' => now()]);
        $path = storage_path('app/rn-stock-test-'.bin2hex(random_bytes(5)).'.csv');
        file_put_contents($path, "Артикул;Наименование;Количество в упаковке;ОПТ;РРЦ\nVM04001;Клапан VARMEGA;10;12.50;99.00\n");
        try {
            $this->artisan('supplier:sync-rn-profi', ['--price-file' => $path, '--apply' => true, '--only-linked' => true])->assertSuccessful();
            $offer->refresh();
            $this->assertNull($offer->stock_quantity);
            $this->assertFalse($offer->in_stock);
            $this->assertSame('unknown', $offer->stock_status);
            $this->assertEquals(10, $offer->raw['package_quantity']);
            $this->assertTrue($offer->raw['quantity_is_package']);
            $this->assertSame(pathinfo($path, PATHINFO_FILENAME), $offer->raw['sheet']);
            $this->assertSame($product->id, $offer->product_id);
            $this->assertSame('VM04001', $offer->supplier_article);
            $this->assertSame('12.50', $offer->price_byn);
            $this->assertSame('15.00', $product->fresh()->price);
            Http::assertNothingSent();
        } finally {
            unlink($path);
        }
    }
}

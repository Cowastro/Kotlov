<?php

namespace Tests\Feature;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\SupplierProductAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierProductAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_one_c_stock_overrides_the_legacy_channel(): void
    {
        $legacySupplier = Supplier::query()->create([
            'code' => 'legacy-stock-test',
            'name' => 'Старый тестовый поставщик',
            'is_active' => true,
        ]);
        $oneCSupplier = Supplier::query()->create([
            'code' => 'onec-stock-test',
            'name' => 'СанБизнесГруп тест',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Категория теста остатков 1С',
            'slug' => 'onec-stock-test-category',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'ONEC-STOCK-1',
            'name' => 'Товар с остатком 1С',
            'slug' => 'onec-stock-product',
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $legacySupplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'LEGACY-STOCK-1',
            'in_stock' => false,
            'stock_quantity' => 0,
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $oneCSupplier->id,
            'code' => 'onec-stock-test',
            'name' => '1С СанБизнесГруп тест',
            'driver' => 'commerceml',
            'is_active' => true,
            'update_stock' => true,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'onec-stock-external-1',
            'stock_quantity' => 15,
            'match_status' => 'matched',
        ]);

        app(SupplierProductAvailabilityService::class)->refresh($product->id);

        $product->refresh();
        $this->assertTrue($product->in_stock);
        $this->assertSame(15, $product->stock_qty);
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $product->availability_status);

        SupplierProduct::query()->where('product_id', $product->id)->update([
            'in_stock' => true,
            'stock_quantity' => 99,
        ]);
        $item->update(['stock_quantity' => 0]);
        app(SupplierProductAvailabilityService::class)->refresh($product->id);

        $product->refresh();
        $this->assertFalse($product->in_stock);
        $this->assertSame(0, $product->stock_qty);
        $this->assertSame(Product::AVAILABILITY_CHECK, $product->availability_status);
    }
}

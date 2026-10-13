<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
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

    public function test_fractional_one_c_stock_remains_available_in_the_integer_product_summary(): void
    {
        $category = Category::query()->create([
            'name' => 'Категория дробного остатка',
            'slug' => 'fractional-stock-category',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'FRACTIONAL-STOCK-1',
            'name' => 'Лист с дробным остатком',
            'slug' => 'fractional-stock-product',
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'onec-fractional-stock-test',
            'name' => '1С дробный остаток',
            'is_active' => true,
            'update_stock' => true,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'fractional-stock-external-1',
            'stock_quantity' => 0.41,
            'match_status' => 'matched',
        ]);

        app(SupplierProductAvailabilityService::class)->refresh($product->id);

        $product->refresh();
        $this->assertTrue($product->in_stock);
        $this->assertSame(1, $product->stock_qty);
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $product->availability_status);
        $this->assertSame('0.410', $item->fresh()->stock_quantity);
    }

    public function test_recent_one_c_exchange_keeps_unchanged_stock_live_and_does_not_add_duplicate_rows(): void
    {
        $category = Category::query()->create([
            'name' => 'Категория системной сверки остатков',
            'slug' => 'systemic-one-c-stock-category',
            'parent_id' => 0,
        ]);
        $single = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'ONEC-UNCHANGED-1',
            'name' => 'Неизменённый товар 1С',
            'slug' => 'onec-unchanged-stock',
            'price' => 10,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $duplicate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'ONEC-DUPLICATE-1',
            'name' => 'Товар с двумя строками одного источника',
            'slug' => 'onec-duplicate-stock',
            'price' => 10,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'onec-systemic-stock-test',
            'name' => '1С системной сверки остатков',
            'driver' => 'commerceml',
            'is_active' => true,
            'update_stock' => true,
            'settings' => ['catalog_interval_minutes' => 10],
        ]);
        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'success',
            'started_at' => now()->subHours(2)->subMinute(),
            'finished_at' => now()->subHours(2),
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $single->id,
            'external_id' => 'onec-unchanged-row',
            'stock_quantity' => 1,
            'stock_confirmed_at' => now()->subDays(2),
            'match_status' => 'matched',
        ]);
        foreach ([6, 4] as $index => $quantity) {
            IntegrationProduct::query()->create([
                'integration_source_id' => $source->id,
                'product_id' => $duplicate->id,
                'external_id' => 'onec-duplicate-row-'.$index,
                'stock_quantity' => $quantity,
                'stock_confirmed_at' => now()->subDays(2),
                'match_status' => 'matched',
            ]);
        }

        app(SupplierProductAvailabilityService::class)->refreshMany([$single->id, $duplicate->id]);

        $single = Product::query()->withSupplierChannels()->findOrFail($single->id);
        $duplicate->refresh();
        $this->assertSame(1, $single->stock_qty);
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $single->effectiveAvailabilityStatus());
        $this->assertTrue($single->canBePurchased());
        $this->assertSame(6, $duplicate->stock_qty);
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $duplicate->availability_status);
    }
}

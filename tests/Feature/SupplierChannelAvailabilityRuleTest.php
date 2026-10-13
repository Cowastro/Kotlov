<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierChannelAvailabilityRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_stock_requires_an_available_linked_supplier_channel(): void
    {
        $unlinked = $this->product('UNLINKED', true);

        $this->assertSame(Product::AVAILABILITY_CHECK, $unlinked->effectiveAvailabilityStatus());

        $supplier = Supplier::query()->create([
            'code' => 'availability-rule-supplier',
            'name' => 'Поставщик правила наличия',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $unlinked->id,
            'supplier_article' => 'LEGACY-LINK-1',
            'in_stock' => true,
            'stock_quantity' => 2,
        ]);

        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $unlinked->fresh()->effectiveAvailabilityStatus());
    }

    public function test_active_matched_1c_channel_can_confirm_stock(): void
    {
        $product = $this->product('ONE-C-LINK', true);
        $supplier = Supplier::query()->create([
            'code' => 'availability-rule-one-c',
            'name' => 'Поставщик 1С правила наличия',
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'availability-rule-one-c-source',
            'name' => '1С правила наличия',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'availability-rule-one-c-item',
            'name' => 'Позиция 1С правила наличия',
            'stock_quantity' => 4,
            'stock_confirmed_at' => now(),
            'match_status' => 'matched',
        ]);

        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $product->fresh()->effectiveAvailabilityStatus());
    }

    public function test_disabled_product_is_out_of_stock_even_when_supplier_has_stock(): void
    {
        $product = $this->product('DISABLED-LINK', false);
        $supplier = Supplier::query()->create([
            'code' => 'disabled-availability-rule',
            'name' => 'Поставщик отключённого товара',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'DISABLED-LINK-1',
            'in_stock' => true,
            'stock_quantity' => 10,
        ]);

        $product = $product->fresh();

        $this->assertSame(Product::AVAILABILITY_OUT_OF_STOCK, $product->effectiveAvailabilityStatus());
        $this->assertFalse($product->canBeOrdered());
    }

    public function test_stale_supplier_stock_requires_consultation(): void
    {
        $product = $this->product('STALE-LINK', true);
        $supplier = Supplier::query()->create([
            'code' => 'stale-availability-rule',
            'name' => 'Поставщик со старым остатком',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'STALE-LINK-1',
            'in_stock' => true,
            'stock_quantity' => 3,
            'last_stock_synced_at' => now()->subHours(SupplierProduct::STALE_AFTER_HOURS + 1),
        ]);

        $this->assertSame(Product::AVAILABILITY_CHECK, $product->fresh()->effectiveAvailabilityStatus());
        $this->assertFalse($product->fresh()->canBePurchased());
    }

    public function test_stale_integration_stock_requires_consultation(): void
    {
        $product = $this->product('STALE-ONE-C', true);
        $supplier = Supplier::query()->create([
            'code' => 'stale-one-c-supplier',
            'name' => 'Поставщик со старым остатком 1С',
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'stale-one-c-source',
            'name' => 'Старый остаток 1С',
            'driver' => 'commerceml',
            'is_active' => true,
            'settings' => ['stale_after_minutes' => 15],
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'stale-one-c-item',
            'name' => 'Позиция со старым остатком 1С',
            'stock_quantity' => 4,
            'stock_confirmed_at' => now()->subMinutes(16),
            'match_status' => 'matched',
        ]);

        $this->assertSame(Product::AVAILABILITY_CHECK, $product->fresh()->effectiveAvailabilityStatus());
        $this->assertFalse($product->fresh()->canBePurchased());
    }

    private function product(string $sku, bool $active): Product
    {
        $category = Category::query()->firstOrCreate(
            ['slug' => 'supplier-channel-rule-products'],
            ['name' => 'Товары правила каналов', 'parent_id' => 0, 'is_active' => true]
        );

        return Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар '.$sku,
            'slug' => strtolower($sku),
            'sku' => $sku,
            'price' => 100,
            'is_active' => $active,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
    }
}

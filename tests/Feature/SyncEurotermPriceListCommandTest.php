<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncEurotermPriceListCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_links_exact_model_and_updates_wholesale_retail_and_stock(): void
    {
        $category = Category::query()->create(['name' => 'Газовые котлы', 'slug' => 'gazovye-kotly']);
        $brand = Brand::query()->create(['name' => 'Navien', 'slug' => 'navien']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Газовый котел Navien Deluxe ONE 24K',
            'slug' => 'navien-deluxe-one-24k-test',
            'sku' => 'Deluxe ONE 24K',
            'price' => 1,
            'is_active' => true,
            'requires_price_confirmation' => true,
        ]);

        $this->artisan('supplier:sync-euroterm', [
            'file' => base_path('resources/data/suppliers/euroterm-2026-10-20.json'),
            '--apply' => true,
        ])->assertSuccessful();

        $supplier = Supplier::query()->where('code', 'euroterm')->firstOrFail();
        $this->assertSame('ООО «Евротерм»', $supplier->name);
        $this->assertNotNull($supplier->crm_counterparty_id);
        $this->assertSame('tce@euroterm.by', $supplier->communication_channels['email']);
        $this->assertDatabaseHas('crm_counterparties', [
            'id' => $supplier->crm_counterparty_id,
            'classification' => 'supplier',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('crm_contact_points', [
            'counterparty_id' => $supplier->crm_counterparty_id,
            'type' => 'email',
            'normalized_value' => 'tce@euroterm.by',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('crm_contact_points', [
            'counterparty_id' => $supplier->crm_counterparty_id,
            'type' => 'phone',
            'normalized_value' => '375172411142',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article_normalized' => 'deluxeone24k',
            'price_byn' => 1950,
            'stock_quantity' => 4,
            'stock_status' => 'in_stock',
            'warehouse_name' => 'Евротерм · Машиностроителей, 9А',
        ]);
        $this->assertDatabaseHas('supplier_sources', [
            'supplier_id' => $supplier->id,
            'name' => 'Прайс и остатки Евротерм',
            'type' => 'excel',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('supplier_syncs', [
            'supplier_id' => $supplier->id,
            'key' => 'euroterm_price',
            'command' => 'supplier:sync-euroterm',
        ]);

        $product->refresh();
        $this->assertSame(2300.0, (float) $product->price);
        $this->assertFalse($product->requires_price_confirmation);
        $this->assertTrue($product->in_stock);
        $this->assertSame(4, $product->stock_qty);
    }
}

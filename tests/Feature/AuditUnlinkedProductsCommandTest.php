<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditUnlinkedProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_only_products_without_a_supplier_channel(): void
    {
        $brand = Brand::query()->create(['name' => 'Энергия', 'slug' => 'energiya']);
        $category = Category::query()->create(['name' => 'ИБП', 'slug' => 'ibp']);

        $unlinked = Product::query()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Энергия Гарант-500',
            'slug' => 'energiya-garant-500',
            'sku' => 'ENERGY-500',
            'price' => 325.41,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
        ]);

        $linked = Product::query()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Энергия Гарант-750',
            'slug' => 'energiya-garant-750',
            'sku' => 'ENERGY-750',
            'price' => 421.84,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
        ]);

        $supplier = Supplier::query()->create([
            'code' => 'test-supplier',
            'name' => 'Test supplier',
            'currency' => 'BYN',
            'is_active' => true,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $linked->id,
            'supplier_article' => 'ENERGY-750',
            'supplier_name' => $linked->name,
            'price' => 700,
            'currency' => 'BYN',
            'price_byn' => 700,
            'in_stock' => true,
        ]);

        $path = storage_path('framework/testing/unlinked-products.csv');

        $this->artisan('catalog:audit-unlinked-products', [
            '--output' => $path,
            '--active-only' => true,
        ])->assertSuccessful();

        $csv = file_get_contents($path);

        $this->assertStringContainsString($unlinked->name, $csv);
        $this->assertStringNotContainsString($linked->name, $csv);
    }

    public function test_operational_audit_includes_non_operational_supplier_links_with_diagnostics(): void
    {
        $brand = Brand::query()->create(['name' => 'Auraton', 'slug' => 'auraton']);
        $category = Category::query()->create(['name' => 'Термостаты', 'slug' => 'termostaty']);
        $product = Product::query()->create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name' => 'Терморегулятор Auraton Test',
            'slug' => 'auraton-test',
            'sku' => 'AURATON-TEST',
            'price' => 100,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'auraton-test-supplier',
            'name' => 'Auraton Test Supplier',
            'currency' => 'BYN',
            'is_active' => false,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'AURATON-TEST',
            'supplier_name' => $product->name,
            'price_byn' => 80,
            'match_confidence' => 'legacy_unverified',
        ]);
        $product->load([
            'supplierProducts.supplier.latestChannelTransition',
            'integrationProducts.source.supplier',
            'purchasePriceSources.supplier',
        ]);
        $this->assertFalse($product->hasAssignedSupplier());

        $path = storage_path('framework/testing/unlinked-operational-products.csv');
        $this->artisan('catalog:audit-unlinked-products', [
            '--output' => $path,
            '--active-only' => true,
            '--operational-only' => true,
            '--brand' => 'Auraton',
        ])->assertSuccessful();

        $csv = file_get_contents($path);
        $this->assertStringContainsString($product->name, $csv);
        $this->assertStringContainsString($supplier->name, $csv);
        $this->assertStringContainsString('confidence=legacy_unverified', $csv);
    }
}

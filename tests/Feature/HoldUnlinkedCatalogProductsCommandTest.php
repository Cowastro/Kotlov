<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoldUnlinkedCatalogProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_holds_only_active_products_without_an_operational_supplier(): void
    {
        Product::query()->update(['is_active' => false]);
        $category = Category::query()->create([
            'name' => 'Проверка непривязанных товаров',
            'slug' => 'unlinked-product-hold',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $unlinked = $this->product($category->id, 'UNLINKED-HOLD');
        $linked = $this->product($category->id, 'LINKED-SAFE');
        $supplier = Supplier::query()->create([
            'code' => 'linked-safe-supplier',
            'name' => 'Проверенный поставщик',
            'is_active' => true,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $linked->id,
            'supplier_article' => 'LINKED-SAFE',
            'price_byn' => 80,
        ]);

        $this->artisan('catalog:hold-unlinked-products')
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.active_products_without_supplier=1')
            ->expectsOutputToContain('metric.products_updated=0')
            ->assertSuccessful();
        $this->assertFalse($unlinked->fresh()->requires_price_confirmation);

        $this->artisan('catalog:hold-unlinked-products', ['--apply' => true])
            ->expectsOutputToContain('metric.status=applied')
            ->expectsOutputToContain('metric.products_updated=1')
            ->assertSuccessful();

        $unlinked->refresh();
        $this->assertTrue($unlinked->requires_price_confirmation);
        $this->assertFalse($unlinked->in_stock);
        $this->assertSame(Product::AVAILABILITY_CHECK, $unlinked->availability_status);
        $this->assertSame('100.00', $unlinked->price);
        $this->assertFalse($unlinked->hasVerifiedPublicPrice());

        $linked->refresh();
        $this->assertFalse($linked->requires_price_confirmation);
        $this->assertTrue($linked->in_stock);
        $this->assertTrue($linked->hasVerifiedPublicPrice());
    }

    private function product(int $categoryId, string $sku): Product
    {
        return Product::query()->create([
            'category_id' => $categoryId,
            'name' => 'Товар '.$sku,
            'slug' => mb_strtolower($sku),
            'sku' => $sku,
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
            'requires_price_confirmation' => false,
        ]);
    }
}

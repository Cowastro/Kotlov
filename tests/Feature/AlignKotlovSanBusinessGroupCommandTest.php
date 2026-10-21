<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlignKotlovSanBusinessGroupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_links_kotlov_to_sanbusinessgroup_and_releases_only_priced_active_cards(): void
    {
        Product::query()->update(['is_active' => false]);
        $existingKotlovBrandIds = Brand::query()
            ->whereRaw('UPPER(name) LIKE ?', ['KOTLOV%'])
            ->pluck('id');
        $existingKotlovProductIds = Product::query()
            ->whereIn('brand_id', $existingKotlovBrandIds)
            ->pluck('id');
        SupplierProduct::query()->whereIn('product_id', $existingKotlovProductIds)->delete();
        Product::query()->whereIn('brand_id', $existingKotlovBrandIds)->delete();
        Brand::query()->whereIn('id', $existingKotlovBrandIds)->delete();
        $category = Category::query()->create([
            'name' => 'Категория KOTLOV',
            'slug' => 'kotlov-alignment-category',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $brand = Brand::query()->updateOrCreate(
            ['slug' => 'kotlov'],
            ['name' => 'KOTLOV', 'is_active' => true],
        );
        $heatPumpBrand = Brand::query()->updateOrCreate(
            ['slug' => 'kotlov-ge'],
            ['name' => 'KOTLOV GE', 'is_active' => true],
        );
        $supplier = Supplier::query()->updateOrCreate(
            ['code' => 'sanbusinessgroup'],
            ['name' => 'ООО «СанБизнесГруп»', 'is_active' => false],
        );
        $priced = $this->product($category->id, $brand->id, 'KOTLOV-PRICED', 250, true, false);
        $withoutPrice = $this->product($category->id, $brand->id, 'KOTLOV-NO-PRICE', 0, true, false);
        $archived = $this->product($category->id, $brand->id, 'KOTLOV-ARCHIVED', 300, true, true);
        $heatPump = $this->product($category->id, $heatPumpBrand->id, 'KOTLOV-GE-FLM30', 8500, true, false);

        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $priced->id,
            'product_sku' => $priced->sku,
            'supplier_article' => 'EXISTING-KOTLOV-LINK',
            'match_status' => 'matched',
        ]);

        $this->artisan('supplier:align-kotlov-sbg')
            ->expectsOutputToContain('metric.status=dry_run')
            ->expectsOutputToContain('metric.brand_count=2')
            ->expectsOutputToContain('metric.brand_products=4')
            ->expectsOutputToContain('metric.supplier_links_to_create=3')
            ->expectsOutputToContain('metric.price_holds_to_release=2')
            ->assertSuccessful();
        $this->assertTrue($priced->fresh()->requires_price_confirmation);
        $this->assertSame(1, SupplierProduct::query()->where('supplier_id', $supplier->id)->count());

        $this->artisan('supplier:align-kotlov-sbg', ['--apply' => true])
            ->expectsOutputToContain('metric.status=applied')
            ->expectsOutputToContain('metric.links_written=4')
            ->expectsOutputToContain('metric.products_released=2')
            ->expectsOutputToContain('metric.operational_products=3')
            ->assertSuccessful();

        $this->assertDatabaseHas('brand_supplier', [
            'brand_id' => $brand->id,
            'supplier_id' => $supplier->id,
        ]);
        $this->assertDatabaseHas('brand_supplier', [
            'brand_id' => $heatPumpBrand->id,
            'supplier_id' => $supplier->id,
        ]);
        $this->assertSame(4, SupplierProduct::query()->where('supplier_id', $supplier->id)->count());
        $this->assertSame(1, SupplierProduct::query()
            ->where('supplier_id', $supplier->id)
            ->where('product_id', $priced->id)
            ->count());
        $this->assertFalse($priced->fresh()->requires_price_confirmation);
        $this->assertSame(Product::AVAILABILITY_CHECK, $priced->fresh()->availability_status);
        $this->assertTrue($priced->fresh()->canBeAddedToCart());
        $this->assertTrue($withoutPrice->fresh()->requires_price_confirmation);
        $this->assertTrue($archived->fresh()->requires_price_confirmation);
        $this->assertFalse($heatPump->fresh()->requires_price_confirmation);
        $this->assertSame(Product::AVAILABILITY_CHECK, $heatPump->fresh()->availability_status);
        $this->assertTrue($heatPump->fresh()->canBeAddedToCart());
        $this->assertTrue($supplier->fresh()->is_active);
    }

    private function product(
        int $categoryId,
        int $brandId,
        string $sku,
        float $price,
        bool $isActive,
        bool $isArchived,
    ): Product {
        return Product::query()->create([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => 'Товар '.$sku,
            'slug' => mb_strtolower($sku),
            'sku' => $sku,
            'price' => $price,
            'is_active' => $isActive,
            'is_archived' => $isArchived,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_OUT_OF_STOCK,
            'requires_price_confirmation' => true,
        ]);
    }
}

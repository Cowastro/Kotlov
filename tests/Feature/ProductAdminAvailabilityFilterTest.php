<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductAdminAvailabilityFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_filters_are_limited_to_fast_search_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $component = Livewire::actingAs($admin)->test(ListProducts::class);

        $this->assertSame(
            ['category_id', 'brand_id', 'supplier', 'in_stock'],
            array_keys($component->instance()->getTable()->getFilters()),
        );
    }

    public function test_in_stock_filter_only_keeps_products_that_are_really_for_sale_on_the_site(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Печи для проверки фильтра',
            'slug' => 'admin-stock-filter-stoves',
            'parent_id' => 0,
            'is_active' => true,
        ]);

        $publiclyInStock = $this->product($category, 'PUBLIC-STOCK', [
            'is_active' => true,
            'price' => 100,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
        $inactiveWithSupplierStock = $this->product($category, 'INACTIVE-STOCK', [
            'is_active' => false,
            'price' => 100,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
        $needsCheck = $this->product($category, 'NEEDS-CHECK', [
            'is_active' => true,
            'price' => 100,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $withoutPrice = $this->product($category, 'WITHOUT-PRICE', [
            'is_active' => true,
            'price' => 0,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
        $explicitlyOut = $this->product($category, 'EXPLICITLY-OUT', [
            'is_active' => true,
            'price' => 100,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_OUT_OF_STOCK,
        ]);
        $unlinkedInStockFlag = $this->product($category, 'UNLINKED-STOCK-FLAG', [
            'is_active' => true,
            'price' => 100,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
        $supplier = Supplier::query()->create([
            'code' => 'admin-stock-filter-supplier',
            'name' => 'Поставщик для фильтра',
        ]);
        foreach ([$publiclyInStock, $inactiveWithSupplierStock, $withoutPrice, $explicitlyOut] as $linkedProduct) {
            SupplierProduct::query()->create([
                'supplier_id' => $supplier->id,
                'product_id' => $linkedProduct->id,
                'supplier_article' => 'FILTER-'.$linkedProduct->sku,
                'in_stock' => true,
                'stock_quantity' => 3,
            ]);
        }

        Livewire::actingAs($admin)
            ->test(ListProducts::class)
            ->filterTable('in_stock', true)
            ->assertCanSeeTableRecords([$publiclyInStock])
            ->assertCanNotSeeTableRecords([
                $inactiveWithSupplierStock,
                $needsCheck,
                $withoutPrice,
                $explicitlyOut,
                $unlinkedInStockFlag,
            ]);

        Livewire::actingAs($admin)
            ->test(ListProducts::class)
            ->filterTable('in_stock', false)
            ->assertCanSeeTableRecords([
                $inactiveWithSupplierStock,
                $needsCheck,
                $withoutPrice,
                $explicitlyOut,
                $unlinkedInStockFlag,
            ])
            ->assertCanNotSeeTableRecords([$publiclyInStock]);

    }

    private function product(Category $category, string $sku, array $attributes): Product
    {
        return Product::query()->create(array_merge([
            'category_id' => $category->id,
            'name' => 'Товар '.$sku,
            'slug' => strtolower(str_replace('_', '-', $sku)),
            'sku' => $sku,
            'is_archived' => false,
        ], $attributes));
    }
}

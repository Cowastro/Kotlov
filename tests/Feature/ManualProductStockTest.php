<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\SupplierProductAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualProductStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_stock_survives_time_and_reconciliation_and_can_be_switched_off(): void
    {
        $category = Category::create(['name' => 'Котлы', 'slug' => 'manual-stock-test', 'parent_id' => 0, 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'KOTLOV', 'slug' => 'manual-kotlov', 'sku' => 'MANUAL-KOTLOV',
            'price' => 100, 'is_active' => true, 'is_archived' => false,
            'in_stock' => true, 'stock_qty' => 2, 'availability_status' => 'in_stock',
            'manual_stock_management' => true,
        ]);
        $automatic = Product::create([
            'category_id' => $category->id,
            'name' => 'Автоматический', 'slug' => 'automatic-stock', 'sku' => 'AUTOMATIC-STOCK',
            'price' => 100, 'is_active' => true, 'is_archived' => false,
            'in_stock' => true, 'availability_status' => 'in_stock',
        ]);
        $supplier = Supplier::create(['code' => 'manual-stock-supplier', 'name' => 'Поставщик', 'is_active' => true]);
        SupplierProduct::create([
            'supplier_id' => $supplier->id, 'product_id' => $product->id,
            'supplier_article' => 'MANUAL-TEST', 'in_stock' => false, 'stock_quantity' => 0,
        ]);

        $this->travel(30)->days();
        app(SupplierProductAvailabilityService::class)->refresh($product->id);
        $this->artisan('supplier:reconcile-availability --apply')->assertSuccessful();
        $this->assertSame('in_stock', $product->fresh()->effectiveAvailabilityStatus());
        $this->assertSame('check', $automatic->fresh()->effectiveAvailabilityStatus());
        $this->assertSame($product->id, Product::whereIn('id', [$product->id, $automatic->id])->prioritizeConfirmedStock()->first()->id);
        $this->postJson('/cart/add', ['product_id' => $product->id])->assertOk();
        $this->assertFalse(session('cart')[$product->id]['requires_availability_confirmation']);

        $product->update(['availability_status' => 'out_of_stock', 'in_stock' => false, 'stock_qty' => 0]);
        $this->assertSame('out_of_stock', $product->fresh()->effectiveAvailabilityStatus());
        $this->postJson('/cart/add', ['product_id' => $product->id])->assertUnprocessable();
        $this->travelBack();
    }
}

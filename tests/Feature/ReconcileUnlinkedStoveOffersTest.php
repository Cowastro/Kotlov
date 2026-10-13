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

class ReconcileUnlinkedStoveOffersTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlinked_stoves_are_changed_to_price_and_availability_on_request(): void
    {
        $stoves = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Печи-камины',
            'slug' => 'pechi-kaminy',
            'is_active' => true,
        ]);
        $accessories = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Каминные аксессуары',
            'slug' => 'aksessuary-kaminy',
            'is_active' => true,
        ]);

        $unlinkedStove = $this->product($stoves, 'UNLINKED-STOVE', 500);
        $linkedStove = $this->product($stoves, 'LINKED-STOVE', 700);
        $unlinkedAccessory = $this->product($accessories, 'UNLINKED-ACCESSORY', 80);

        $supplier = Supplier::query()->create([
            'code' => 'active-stove-supplier',
            'name' => 'Поставщик печей',
            'is_active' => true,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $linkedStove->id,
            'product_sku' => $linkedStove->sku,
            'supplier_article' => 'SUP-LINKED-STOVE',
            'price' => 500,
            'price_byn' => 500,
            'in_stock' => true,
            'stock_status' => 'in_stock',
        ]);

        $this->artisan('supplier:reconcile-availability', ['--apply' => true])
            ->assertSuccessful();

        $unlinkedStove->refresh();
        $linkedStove->refresh();
        $unlinkedAccessory->refresh();

        $this->assertSame('0.00', $unlinkedStove->price);
        $this->assertFalse($unlinkedStove->in_stock);
        $this->assertNull($unlinkedStove->stock_qty);
        $this->assertSame(Product::AVAILABILITY_CHECK, $unlinkedStove->availability_status);

        $this->assertSame('700.00', $linkedStove->price);
        $this->assertTrue($linkedStove->in_stock);
        $this->assertSame('80.00', $unlinkedAccessory->price);
        $this->assertTrue($unlinkedAccessory->in_stock);
    }

    public function test_one_c_stock_is_authoritative_over_a_legacy_supplier_in_the_final_audit(): void
    {
        $category = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Тест приоритета 1С',
            'slug' => 'onec-priority-reconcile-test',
            'is_active' => true,
        ]);
        $product = $this->product($category, 'ONEC-PRIORITY-RECONCILE', 100);
        $supplier = Supplier::query()->create([
            'code' => 'legacy-priority-test',
            'name' => 'Старый канал наличия',
            'is_active' => true,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'LEGACY-POSITIVE',
            'in_stock' => true,
            'stock_quantity' => 9,
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'onec-priority-reconcile-test',
            'name' => '1С приоритетного остатка',
            'driver' => 'commerceml',
            'is_active' => true,
            'update_stock' => true,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'ONEC-ZERO',
            'match_status' => 'matched',
            'stock_quantity' => 0,
        ]);

        $this->artisan('supplier:reconcile-availability', ['--apply' => true])
            ->assertSuccessful();

        $product->refresh();
        $this->assertFalse($product->in_stock);
        $this->assertSame(0, $product->stock_qty);
        $this->assertSame(Product::AVAILABILITY_CHECK, $product->availability_status);
    }

    private function product(Category $category, string $sku, int $price): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'name' => $sku,
            'slug' => strtolower($sku),
            'sku' => $sku,
            'price' => $price,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'stock_qty' => 4,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
    }
}

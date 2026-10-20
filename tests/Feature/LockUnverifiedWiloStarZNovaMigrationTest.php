<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LockUnverifiedWiloStarZNovaMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_hides_the_unverified_price_and_records_wilo_bel_without_opening_a_product_channel(): void
    {
        $now = now();
        $brandId = DB::table('brands')->insertGetId([
            'name' => 'WILO',
            'slug' => 'wilo',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Циркуляционные насосы',
            'slug' => 'wilo-star-z-safety-test',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $productId = DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => 'Насос WILO Star-Z NOVA (ROW) (4132760)',
            'slug' => 'wilo-star-z-nova-safety-test',
            'sku' => 'PS-010.108',
            'price' => 275,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => 'in_stock',
            'requires_price_confirmation' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $migration = require database_path('migrations/2026_10_21_020000_lock_unverified_wilo_star_z_nova.php');
        $migration->up();

        $product = DB::table('products')->find($productId);
        $this->assertSame(0.0, (float) $product->price);
        $this->assertTrue((bool) $product->requires_price_confirmation);
        $this->assertFalse((bool) $product->in_stock);
        $this->assertNull($product->stock_qty);
        $this->assertSame('check', $product->availability_status);

        $supplier = DB::table('suppliers')->where('code', 'wilo-bel')->firstOrFail();
        $this->assertSame('ИООО «ВИЛО БЕЛ»', $supplier->name);
        $this->assertDatabaseHas('brand_supplier', [
            'brand_id' => $brandId,
            'supplier_id' => $supplier->id,
        ]);
        $this->assertDatabaseMissing('supplier_products', [
            'product_id' => $productId,
            'supplier_id' => $supplier->id,
        ]);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConfirmMercuryKstb951tStockMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_confirms_seven_units_for_mercury_kstb_95_1t(): void
    {
        $now = now();
        $brandId = (int) DB::table('brands')->where('name', 'Меркурий')->value('id');
        if ($brandId === 0) {
            $brandId = (int) DB::table('brands')->insertGetId([
                'name' => 'Меркурий',
                'slug' => 'merkuriy-stock-test',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Твердотопливные котлы',
            'slug' => 'tverdotoplivnye-mercury-stock-test',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('products')->insert([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => 'Старое название',
            'slug' => 'mercury-kstb-95-1t-stock-test',
            'sku' => 'PS-011.554',
            'price' => 1,
            'currency' => 'BYN',
            'is_active' => true,
            'in_stock' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $catalogMigration = require database_path('migrations/2026_10_15_080000_link_mercury_kstb_catalog.php');
        $catalogMigration->up();

        $stockMigration = require database_path('migrations/2026_10_15_090000_confirm_mercury_kstb_95_1t_stock.php');
        $stockMigration->up();

        $product = DB::table('products')->where('sku', 'PS-011.554')->firstOrFail();
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame(7, (int) $product->stock_qty);
        $this->assertSame('in_stock', $product->availability_status);
        $this->assertFalse((bool) $product->requires_price_confirmation);
        $this->assertSame(8490.00, (float) $product->price);

        $supplierId = (int) DB::table('suppliers')->where('code', 'mercury')->value('id');
        $offer = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $this->assertTrue((bool) $offer->in_stock);
        $this->assertSame(7, (int) $offer->stock_quantity);
        $this->assertSame('in_stock', $offer->stock_status);
        $this->assertSame('В наличии: 7 шт.', $offer->stock_text);

        $raw = json_decode((string) $offer->raw, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('official_manufacturer_catalog', $raw['source']);
        $this->assertSame('manual_confirmation', $raw['stock_source']);
        $this->assertSame(7, $raw['stock_confirmed_quantity']);
        $this->assertSame('2026-10-15', $raw['stock_checked_at']);
    }
}

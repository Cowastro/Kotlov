<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LinkMercuryKstbCatalogMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_links_and_reprices_all_seventeen_mercury_kstb_cards(): void
    {
        $now = now();
        $brandId = (int) DB::table('brands')->where('name', 'Меркурий')->value('id');
        if ($brandId === 0) {
            $brandId = (int) DB::table('brands')->insertGetId([
                'name' => 'Меркурий',
                'slug' => 'merkuriy',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Твердотопливные',
            'slug' => 'tverdotoplivnye-mercury-test',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (range(540, 556) as $suffix) {
            DB::table('products')->insert([
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'name' => 'Старое название '.$suffix,
                'slug' => 'mercury-kstb-test-'.$suffix,
                'sku' => sprintf('PS-011.%d', $suffix),
                'price' => 1,
                'currency' => 'BYN',
                'is_active' => true,
                'in_stock' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $migration = require database_path('migrations/2026_10_15_080000_link_mercury_kstb_catalog.php');
        $migration->up();

        $supplierId = (int) DB::table('suppliers')->where('code', 'mercury')->value('id');

        $this->assertGreaterThan(0, $supplierId);
        $this->assertSame(17, DB::table('supplier_products')->where('supplier_id', $supplierId)->count());
        $this->assertDatabaseHas('brand_supplier', [
            'brand_id' => $brandId,
            'supplier_id' => $supplierId,
        ]);
        $this->assertDatabaseHas('supplier_sources', [
            'supplier_id' => $supplierId,
            'name' => 'Официальный каталог КСТБ',
            'type' => 'website',
        ]);
        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplierId,
            'supplier_article' => 'КСТБ 95-1Т',
            'supplier_name' => 'Твердотопливный котел КСТБ 95-1Т',
            'price_byn' => 8490.00,
            'match_status' => 'matched',
        ]);

        $product = DB::table('products')->where('sku', 'PS-011.554')->firstOrFail();
        $this->assertSame('Твердотопливный котел КСТБ 95-1Т', $product->name);
        $this->assertSame($product->name, $product->h1);
        $this->assertSame(8490.00, (float) $product->price);
        $this->assertSame('https://pkmercury.by/product/tverdotoplivnyj-kotel-kstb-95-1t/', $product->content_source_url);
        $this->assertSame('check', $product->availability_status);
        $this->assertTrue((bool) $product->requires_price_confirmation);
        $this->assertFalse((bool) $product->in_stock);

        $this->assertSame(4, DB::table('products')->whereIn('sku', [
            'PS-011.543', 'PS-011.544', 'PS-011.545', 'PS-011.546',
        ])->where('price', 3660)->count());
        $this->assertSame(4, DB::table('products')->whereIn('sku', [
            'PS-011.542', 'PS-011.547', 'PS-011.548', 'PS-011.549',
        ])->where('price', 5640)->count());
        $this->assertSame(5, DB::table('products')->whereIn('sku', [
            'PS-011.541', 'PS-011.550', 'PS-011.551', 'PS-011.552', 'PS-011.553',
        ])->where('price', 7290)->count());
        $this->assertSame(4, DB::table('products')->whereIn('sku', [
            'PS-011.540', 'PS-011.554', 'PS-011.555', 'PS-011.556',
        ])->where('price', 8490)->count());
    }
}

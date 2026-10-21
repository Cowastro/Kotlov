<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NormalizeGasMeterSupplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_elicon_and_assigns_the_sanbusinessgroup_warehouse_to_gas_meters(): void
    {
        $category = Category::query()->updateOrCreate(
            ['slug' => 'schetchiki-gaza'],
            ['name' => 'Счетчики газа', 'parent_id' => 0, 'is_active' => true],
        );
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Счетчик газа ультразвуковой КАТА G4-3',
            'slug' => 'test-kata-g4-3',
            'sku' => 'KOTLOV-TEST-KATA',
            'price' => 470.88,
            'content' => 'Актуализировано по каталогу elicon.by, категория счетчиков.',
            'short_description' => 'Артикул поставщика Эликон: 8348000000008.',
            'meta_keywords' => 'счетчик газа, БелОМО, Эликон, 8348000000008',
            'specs' => [
                'supplier' => 'Эликон',
                'supplier_article' => '8348000000008',
                'source_url' => 'https://elicon.by/example',
                'g_size' => 'G4',
            ],
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
        ]);
        $elicon = Supplier::query()->updateOrCreate(['code' => 'elicon'], ['name' => 'Эликон', 'is_active' => true]);
        $belomo = Supplier::query()->updateOrCreate(['code' => 'belomo'], ['name' => 'БелОМО', 'is_active' => true]);

        SupplierProduct::query()->create([
            'supplier_id' => $elicon->id,
            'product_id' => $product->id,
            'supplier_article' => '8348000000008',
            'price_byn' => 433.32,
            'warehouse_name' => 'Эликон',
            'match_status' => 'matched',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $belomo->id,
            'product_id' => $product->id,
            'supplier_article' => '8348 0000000 08',
            'price_byn' => 327,
            'warehouse_name' => 'СанБизнесГруп · ожидается поступление',
            'match_status' => 'matched',
        ]);
        DB::table('supplier_product_mappings')->insert([
            'supplier_code' => 'elicon',
            'supplier_article' => '8348000000008',
            'product_id' => $product->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_21_120000_remove_elicon_from_gas_meters_and_normalize_warehouse.php');
        $migration->up();

        $this->assertDatabaseMissing('supplier_products', [
            'supplier_id' => $elicon->id,
            'product_id' => $product->id,
        ]);
        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $belomo->id,
            'product_id' => $product->id,
            'warehouse_name' => 'СанБизнесГруп',
            'price_byn' => 327,
        ]);
        $this->assertDatabaseMissing('supplier_product_mappings', [
            'supplier_code' => 'elicon',
            'product_id' => $product->id,
        ]);
        $this->assertFalse($elicon->fresh()->is_active);
        $this->assertSame(470.88, (float) $product->fresh()->price);
        $this->assertStringNotContainsString('Эликон', implode(' ', [
            $product->fresh()->content,
            $product->fresh()->short_description,
            $product->fresh()->meta_keywords,
            json_encode($product->fresh()->specs, JSON_UNESCAPED_UNICODE),
        ]));
        $this->assertSame('G4', $product->fresh()->specs['g_size']);
        $this->assertSame('8348000000008', $product->fresh()->specs['supplier_article']);
    }
}

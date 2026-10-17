<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RefreshZordGasDetectorCatalogMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reprices_both_detectors_and_removes_the_unrelated_importer(): void
    {
        $category = Category::query()->create([
            'name' => 'Сигнализаторы загазованности',
            'slug' => 'zord-migration-test',
            'parent_id' => 0,
        ]);

        $ugs03 = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-008.168',
            'name' => 'Старая карточка УГС-03',
            'slug' => 'old-zord-ugs-03',
            'price' => 42,
        ]);
        $ugs02 = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-011.094',
            'name' => 'Старая карточка УГС-02',
            'slug' => 'old-zord-ugs-02',
            'price' => 130,
        ]);

        $importerAttributeId = DB::table('attributes')->insertGetId([
            'category_id' => $category->id,
            'group_id' => 0,
            'sort_order' => 1,
            'type' => 'value',
            'name' => 'Импортер',
            'in_filter' => false,
            'in_sort' => false,
            'in_product' => true,
            'in_brief' => false,
            'is_comparable' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ([$ugs03, $ugs02] as $product) {
            DB::table('product_attribute_values')->insert([
                'product_id' => $product->id,
                'attribute_id' => $importerAttributeId,
                'value' => 'ООО "ГазТеплоТорг"',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration = require database_path('migrations/2026_10_17_120000_refresh_zord_gas_detector_catalog.php');
        $migration->up();

        $ugs03->refresh();
        $ugs02->refresh();

        $this->assertSame('85.00', $ugs03->price);
        $this->assertSame('105.00', $ugs02->price);
        $this->assertSame('ООО «ЗОРД Электротеплоприбор»', collect($ugs03->specs)->firstWhere('key', 'Производитель')['value']);
        $this->assertSame('ООО «ЗОРД Электротеплоприбор»', collect($ugs02->specs)->firstWhere('key', 'Производитель')['value']);
        $this->assertDatabaseMissing('product_attribute_values', ['value' => 'ООО "ГазТеплоТорг"']);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $ugs03->id,
            'value' => 'ООО «ЗОРД Электротеплоприбор»',
        ]);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $ugs02->id,
            'value' => 'Беларусь',
        ]);
    }
}

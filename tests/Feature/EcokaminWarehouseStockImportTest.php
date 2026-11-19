<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\Supplier;
use App\Services\EcokaminWarehouseStockImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcokaminWarehouseStockImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_matches_only_ecokamin_products_and_uses_available_quantity(): void
    {
        $category = Category::create(['name' => 'Камины', 'slug' => 'kaminy']);
        $ecokamin = Brand::create(['name' => 'ЭкоКамин', 'slug' => 'ecokamin']);
        $otherBrand = Brand::create(['name' => 'Другой', 'slug' => 'other-brand']);
        $supplier = Supplier::query()->updateOrCreate(
            ['code' => 'sanbusinessgroup'],
            ['name' => 'ООО «СанБизнесГруп»', 'is_active' => true],
        );
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $ecokamin->id,
            'name' => 'Камин МАДРИД на дровнике',
            'slug' => 'kamin-madrid',
            'sku' => 'KM203',
            'price' => 100,
            'is_active' => true,
        ]);
        Product::create([
            'category_id' => $category->id,
            'brand_id' => $otherBrand->id,
            'name' => 'Чужой товар',
            'slug' => 'other-km203',
            'sku' => 'KM203',
            'price' => 100,
            'is_active' => true,
        ]);

        $result = app(EcokaminWarehouseStockImporter::class)->importPayload([
            'as_of_date' => '2026-10-20',
            'warehouse_code' => 'ecokamin-moscow',
            'warehouse_name' => 'Склад Москва',
            'delivery_days' => 7,
            'source_file' => 'остатки на 20.10.xlsx',
            'items' => [[
                'article' => 'KM203',
                'name' => 'Камин МАДРИД на дровнике',
                'total' => 8,
                'reserved' => 2,
                'shipping' => 1,
                'available' => 5,
            ]],
        ]);

        $this->assertSame(1, $result['matched']);
        $this->assertSame(0, $result['unmatched']);
        $this->assertDatabaseHas('product_warehouse_stocks', [
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'warehouse_name' => 'Склад Москва',
            'total_quantity' => 8,
            'reserved_quantity' => 2,
            'shipping_quantity' => 1,
            'available_quantity' => 5,
            'delivery_days' => 7,
        ]);
        $this->assertSame(
            '2026-10-20',
            $product->warehouseStocks()->firstOrFail()->as_of_date->toDateString(),
        );
    }

    public function test_new_complete_snapshot_zeroes_previous_moscow_stock_not_present_in_file(): void
    {
        $category = Category::create(['name' => 'Камины', 'slug' => 'kaminy']);
        $brand = Brand::create(['name' => 'ЭкоКамин', 'slug' => 'ecokamin']);
        Supplier::query()->updateOrCreate(
            ['code' => 'sanbusinessgroup'],
            ['name' => 'ООО «СанБизнесГруп»', 'is_active' => true],
        );
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Камин РИО',
            'slug' => 'kamin-rio',
            'sku' => 'KR206',
            'price' => 100,
            'is_active' => true,
        ]);
        Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Камин МАДРИД',
            'slug' => 'kamin-madrid-second',
            'sku' => 'KM203',
            'price' => 100,
            'is_active' => true,
        ]);
        $importer = app(EcokaminWarehouseStockImporter::class);
        $base = [
            'as_of_date' => '2026-10-20',
            'warehouse_code' => 'ecokamin-moscow',
            'warehouse_name' => 'Склад Москва',
            'delivery_days' => 7,
            'source_file' => 'stock.xlsx',
        ];

        $importer->importPayload($base + ['items' => [[
            'article' => 'KR206', 'name' => 'Камин РИО', 'available' => 3,
        ]]]);
        $result = $importer->importPayload($base + ['items' => [[
            'article' => 'KM203', 'name' => 'Камин МАДРИД', 'available' => 4,
        ]]]);

        $this->assertSame(1, $result['zeroed']);
        $this->assertDatabaseHas('product_warehouse_stocks', [
            'product_id' => $product->id,
            'available_quantity' => 0,
        ]);
    }

    public function test_import_uses_sanbusinessgroup_and_drops_rows_of_the_retired_duplicate(): void
    {
        $category = Category::create(['name' => 'Камины', 'slug' => 'kaminy']);
        $brand = Brand::create(['name' => 'ЭкоКамин', 'slug' => 'ecokamin']);
        $retired = Supplier::query()->updateOrCreate(
            ['code' => 'ecokamin-merged-into-sanbusinessgroup'],
            ['name' => 'ЭкоКамин (объединён)', 'is_active' => false],
        );
        $sbg = Supplier::query()->updateOrCreate(
            ['code' => 'sanbusinessgroup'],
            ['name' => 'ООО «СанБизнесГруп»', 'is_active' => true],
        );
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Камин ПРАГА',
            'slug' => 'kamin-praga-duplicate-supplier',
            'sku' => 'KP100',
            'price' => 100,
            'is_active' => true,
        ]);
        ProductWarehouseStock::query()->create([
            'product_id' => $product->id,
            'supplier_id' => $retired->id,
            'warehouse_code' => 'ecokamin-moscow',
            'warehouse_name' => 'Склад Москва',
            'available_quantity' => 9,
        ]);

        app(EcokaminWarehouseStockImporter::class)->importPayload([
            'warehouse_code' => 'ecokamin-moscow',
            'items' => [['article' => 'KP100', 'name' => 'Камин ПРАГА', 'total' => 3, 'available' => 3]],
        ]);

        $this->assertSame(1, ProductWarehouseStock::query()->where('warehouse_code', 'ecokamin-moscow')->count());
        $this->assertDatabaseHas('product_warehouse_stocks', [
            'product_id' => $product->id,
            'supplier_id' => $sbg->id,
            'available_quantity' => 3,
        ]);
    }

    public function test_a_prepared_snapshot_can_be_checked_on_the_server_without_writing(): void
    {
        $category = Category::create(['name' => 'Топки', 'slug' => 'topki-preview']);
        $brand = Brand::create(['name' => 'ЭкоКамин', 'slug' => 'ecokamin']);
        Supplier::query()->updateOrCreate(['code' => 'sanbusinessgroup'], ['name' => 'ООО «СанБизнесГруп»', 'is_active' => true]);
        $card = fn (string $name, string $slug, string $sku) => Product::create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => $name, 'slug' => $slug, 'sku' => $sku, 'price' => 100, 'is_active' => true]);
        $alfa = $card('Топка АЛЬФА 1000ТРВ', 'preview-alfa', 'TAN1000TPB');
        $first = $card('Топка ДЕЛЬТА 800B', 'preview-delta-b', 'TDN800B');
        $second = $card('Топка ДЕЛЬТА 800PВ', 'preview-delta-pb', 'TDN800B');
        $path = storage_path('framework/testing-ecokamin-preview.json');
        file_put_contents($path, json_encode(['as_of_date' => '2026-11-19', 'items' => [
            ['article' => 'TAN1000TPB', 'name' => 'Топка АЛЬФА 1000ТРВ туннельная', 'available' => 2],
            ['article' => 'TDN800B', 'name' => 'Топка ДЕЛЬТА 800B три стекла', 'available' => 1],
            ['article' => 'KR226', 'name' => 'Камин РИО керамика', 'available' => 1],
        ]], JSON_UNESCAPED_UNICODE));

        $this->artisan('supplier:import-ecokamin-moscow-stock', ['file' => $path, '--show' => ['TAN1000TPB']])
            ->expectsOutputToContain('metric.ecokamin_stock.unmatched=2')
            ->expectsOutputToContain('audit.ecokamin_stock.row=TAN1000TPB:2:'.$alfa->id.':article')
            ->expectsOutputToContain('audit.ecokamin_stock.ambiguous=TDN800B:1:'.$first->id.','.$second->id)
            ->expectsOutputToContain('audit.ecokamin_stock.unmatched=KR226:1')
            ->assertSuccessful();
        @unlink($path);
        $this->assertSame(0, ProductWarehouseStock::query()->count());
    }
}

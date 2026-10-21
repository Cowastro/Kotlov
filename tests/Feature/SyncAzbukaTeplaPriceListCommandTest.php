<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SyncAzbukaTeplaPriceListCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_unblocks_existing_product_with_public_order_and_internal_stock_confirmation(): void
    {
        $category = Category::query()->create(['name' => 'Газовые котлы', 'slug' => 'gazovye']);
        $brand = Brand::query()->create(['name' => 'Vaillant', 'slug' => 'vaillant']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Газовый котел Vaillant VUW 242/5-3',
            'slug' => 'vaillant-vuw-242-5-3-azbuka-test',
            'sku' => 'VUW 242/5-3',
            'price' => 1,
            'is_active' => false,
            'is_archived' => true,
            'in_stock' => true,
            'stock_qty' => 99,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
            'requires_price_confirmation' => true,
        ]);

        $path = $this->snapshot([[
            'source_row' => 2,
            'brand' => 'Vaillant',
            'description' => 'котел газовый',
            'model' => 'VUW 242/5-3',
            'retail_byn' => 4100,
            'purchase_byn' => 3362,
            'partner_name' => 'VUW 242/5-3 газовый настенный котел',
            'partner_description' => '24 кВт',
        ]]);

        $this->artisan('supplier:sync-azbuka-tepla', ['file' => $path, '--apply' => true])
            ->assertSuccessful();

        $supplier = Supplier::query()->where('code', 'azbuka-tepla')->firstOrFail();
        $product->refresh()->load('supplierProducts.supplier');
        $this->assertTrue($product->is_active);
        $this->assertFalse($product->is_archived);
        $this->assertFalse($product->requires_price_confirmation);
        $this->assertSame(4100.0, (float) $product->price);
        $this->assertSame('24 кВт', $product->short_description);
        $this->assertSame('VUW 242/5-3', $product->specs[1]['value']);
        $this->assertFalse($product->in_stock);
        $this->assertNull($product->stock_qty);
        $this->assertSame(Product::AVAILABILITY_CHECK, $product->availability_status);
        $this->assertSame('Доступно к заказу', $product->publicAvailabilityLabel());
        $this->assertTrue($product->canBeAddedToCart());

        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'price_byn' => 3362,
            'in_stock' => false,
            'stock_quantity' => null,
            'stock_status' => 'check_availability',
            'stock_text' => 'Уточнить наличие',
            'match_confidence' => 'manual_price_list',
        ]);
        $this->assertDatabaseHas('supplier_sources', [
            'supplier_id' => $supplier->id,
            'name' => 'Азбука Тепла — розничные цены',
            'type' => 'google_sheet',
        ]);
        $this->assertDatabaseHas('supplier_sources', [
            'supplier_id' => $supplier->id,
            'name' => 'Азбука Тепла — партнерский прайс Опт VIP',
            'type' => 'excel',
        ]);
    }

    public function test_it_creates_a_missing_card_without_inventing_stock(): void
    {
        $category = Category::query()->create([
            'name' => 'Автоматика и терморегуляторы',
            'slug' => 'avtomatika-i-termoregulyatory',
        ]);
        $path = $this->snapshot([[
            'source_row' => 249,
            'brand' => 'Auraton',
            'description' => 'термостат комнатный',
            'model' => 'Auriga',
            'retail_byn' => 145,
            'purchase_byn' => 108.68,
            'partner_name' => 'Auriga Проводной регулятор температуры Auraton',
            'partner_description' => 'Электронный регулятор температуры, ЖК дисплей.',
        ]]);

        $this->artisan('supplier:sync-azbuka-tepla', ['file' => $path, '--apply' => true])
            ->assertSuccessful();

        $product = Product::query()->where('name', 'Комнатный термостат Auraton Auriga')->firstOrFail();
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame(145.0, (float) $product->price);
        $this->assertSame(Product::AVAILABILITY_CHECK, $product->availability_status);
        $this->assertNull($product->stock_qty);
        $this->assertSame('Доступно к заказу', $product->publicAvailabilityLabel());
        $this->assertTrue($product->canBeAddedToCart());
        $this->assertDatabaseHas('supplier_products', [
            'product_id' => $product->id,
            'price_byn' => 108.68,
            'stock_text' => 'Уточнить наличие',
        ]);
    }

    public function test_it_relinks_protherm_skat_to_the_existing_card_and_archives_import_duplicate(): void
    {
        $category = Category::query()->create(['name' => 'Электрические котлы', 'slug' => 'elektricheskie']);
        $brand = Brand::query()->create(['name' => 'Protherm', 'slug' => 'protherm']);
        $supplier = Supplier::query()->create([
            'code' => 'azbuka-tepla',
            'name' => 'Азбука Тепла',
            'is_active' => true,
        ]);
        $canonical = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Электрический котел Protherm Скат 14К (Ray)',
            'slug' => 'protherm-skat-14k-ray',
            'sku' => 'PROTHERM-SKAT-14',
            'price' => 1,
            'is_active' => true,
            'is_archived' => false,
            'requires_price_confirmation' => true,
        ]);
        foreach ([12, 18] as $power) {
            Product::query()->create([
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'name' => "Электрический котел Protherm Скат {$power}К (Ray)",
                'slug' => "protherm-skat-{$power}k-ray",
                'sku' => "PROTHERM-SKAT-{$power}",
                'price' => 1,
                'is_active' => true,
                'is_archived' => false,
                'requires_price_confirmation' => true,
            ]);
        }
        $duplicate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'supplier_id' => $supplier->id,
            'name' => 'Электрический котел Protherm 14 Скат',
            'slug' => 'elektricheskiy-kotel-protherm-14-skat',
            'sku' => 'KOTLOV-099999',
            'price' => 3550,
            'is_active' => true,
            'is_archived' => false,
            'requires_price_confirmation' => false,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $duplicate->id,
            'product_sku' => $duplicate->sku,
            'supplier_article' => 'Protherm 14 Скат',
            'supplier_article_normalized' => 'protherm14ckat',
            'match_status' => 'matched',
        ]);

        $path = $this->snapshot([[
            'source_row' => 31,
            'brand' => 'Protherm',
            'description' => 'котел электрический',
            'model' => '14 Скат',
            'retail_byn' => 3550,
            'purchase_byn' => 2911,
            'partner_name' => 'RAY 14 KE/14 Настенный электрический котел, Protherm',
            'partner_description' => 'Электрический котел 2,3-14 кВт',
        ]]);

        $this->artisan('supplier:sync-azbuka-tepla', ['file' => $path, '--apply' => true])
            ->expectsOutputToContain('metric.azbuka_tepla.duplicates_archived=1')
            ->assertSuccessful();

        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplier->id,
            'product_id' => $canonical->id,
            'supplier_article_normalized' => 'protherm14ckat',
            'price_byn' => 2911,
        ]);
        $this->assertSame(3550.0, (float) $canonical->fresh()->price);
        $this->assertFalse($canonical->fresh()->requires_price_confirmation);
        $this->assertFalse($duplicate->fresh()->is_active);
        $this->assertTrue($duplicate->fresh()->is_archived);
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function snapshot(array $rows): string
    {
        $path = storage_path('framework/testing/azbuka-tepla-test.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'supplier' => 'Азбука Тепла',
            'source_date' => '2026-10-20',
            'retail_rows' => count($rows),
            'matched_rows' => count($rows),
            'review_rows' => 0,
            'partner_source_file' => 'Азбука Тепла с 20 октября 2026.xlsm',
            'rows' => $rows,
            'review' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }
}

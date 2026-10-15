<?php

namespace Tests\Feature;

use App\Console\Commands\ImportOleProPurchasePricesCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\ProductPurchasePriceSource;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportOleProPurchasePricesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_document_costs_idempotently_without_saving_retail_or_exchange_data(): void
    {
        $supplier = Supplier::query()->create(['code' => 'sbg-cost', 'name' => 'ООО «СанБизнесГруп»']);
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-cost', 'name' => '1С СанБизнесГруп', 'supplier_id' => $supplier->id,
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'], 'is_active' => true,
        ]);
        $category = Category::query()->create(['name' => 'Гидравлика', 'slug' => 'ole-cost-test', 'parent_id' => 0]);
        $brand = Brand::query()->create(['name' => 'OLE-PRO', 'slug' => 'ole-pro']);
        $before = [];
        foreach ((new \ReflectionClass(ImportOleProPurchasePricesCommand::class))->getConstant('ROWS') as $code => [$price, $sku, $model]) {
            $product = $sku ? Product::query()->create([
                'category_id' => $category->id, 'brand_id' => $brand->id,
                'sku' => $sku, 'slug' => 'cost-'.strtolower($sku), 'name' => 'OLE-PRO '.$model,
                'price' => 466.08, 'stock_qty' => 5, 'in_stock' => true,
                'requires_price_confirmation' => true,
            ]) : null;
            $item = IntegrationProduct::query()->create([
                'integration_source_id' => $source->id, 'external_code' => $code,
                'external_id' => $code, 'name' => $model, 'price_byn' => 99.99,
                'stock_quantity' => 5, 'product_id' => $product?->id,
                'match_status' => $product ? 'matched' : 'unmatched',
            ]);
            $before[] = [$item, $item->fresh()->getAttributes(), $product, $product?->fresh()->getAttributes()];
        }
        $sourceBefore = $source->fresh()->getAttributes();
        $this->artisan('catalog:import-ole-pro-purchase-prices')->assertSuccessful();
        $this->assertSame(0, ProductPurchasePriceSource::query()->count());
        $this->artisan('catalog:import-ole-pro-purchase-prices', ['--apply' => true])->assertSuccessful();
        $this->assertSame(10, ProductPurchasePriceSource::query()->count());
        $cost = ProductPurchasePriceSource::query()->where('product_id', $before[0][2]->id)->firstOrFail();
        $this->assertSame('100.53', $cost->source_price);
        $this->assertSame('120.64', $cost->price_byn);
        $this->assertSame('exclusive', $cost->price_tax_mode);
        $this->assertSame('2026-03-13', $cost->document_date->toDateString());
        $costBefore = $cost->getAttributes();
        $this->artisan('catalog:import-ole-pro-purchase-prices', ['--apply' => true])->assertSuccessful();
        $this->assertSame(10, ProductPurchasePriceSource::query()->count());
        $this->assertSame($costBefore, $cost->fresh()->getAttributes());
        foreach ($before as [$item, $itemBefore, $product, $productBefore]) {
            $this->assertSame($itemBefore, $item->fresh()->getAttributes());
            if ($product) {
                $this->assertSame($productBefore, $product->fresh()->getAttributes());
            }
        }
        $this->assertSame($sourceBefore, $source->fresh()->getAttributes());
    }
}

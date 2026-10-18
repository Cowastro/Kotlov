<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CrmContactPoint;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncTeplouchetCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_is_read_only_and_apply_imports_prices_stock_contacts_and_analogs(): void
    {
        $brand = Brand::create(['name' => 'DAB', 'slug' => 'dab-test', 'is_active' => true]);
        $category = Category::create(['name' => 'Насосы', 'slug' => 'dab-test']);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Циркуляционный насос DAB A 50/180 M',
            'slug' => 'dab-a-50-180-m-test',
            'sku' => '505803001',
            'price' => 1300,
            'is_active' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $minskProduct = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Поверхностный насос DAB B 50/250.40 M',
            'slug' => 'dab-b-50-250-40-m-test',
            'sku' => 'KOTLOV-DAB-B-50-250',
            'price' => 9000,
            'is_active' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $misbrandedProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Старая карточка без указанного бренда',
            'slug' => 'dab-a-50-180-xm-without-brand-test',
            'sku' => '505802041',
            'price' => 1300,
            'is_active' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $wiloBrand = Brand::create(['name' => 'Wilo', 'slug' => 'wilo-test', 'is_active' => true]);
        $wiloAnalog = Product::create([
            'category_id' => $category->id,
            'brand_id' => $wiloBrand->id,
            'name' => 'Циркуляционный насос WILO TOP-S 25/5 EM PN6/10',
            'slug' => 'wilo-top-s-25-5-em-test',
            'sku' => 'WILO-TOP-S-25-5-EM',
            'price' => 1500,
            'is_active' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $referenceOnlyDab = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Циркуляционный насос DAB VA 25/130',
            'slug' => 'dab-va-25-130-reference-test',
            'sku' => '505100962',
            'price' => 500,
            'is_active' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $referenceOnlyWilo = Product::create([
            'category_id' => $category->id,
            'brand_id' => $wiloBrand->id,
            'name' => 'Циркуляционный насос Wilo Star-RS 25/4-130',
            'slug' => 'wilo-star-rs-25-4-130-reference-test',
            'sku' => 'WILO-STAR-RS-25-4-130',
            'price' => 500,
            'is_active' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);

        $this->artisan('supplier:sync-teplouchet', ['--dry-run' => true, '--match-existing' => true])
            ->expectsOutput('metric.teplouchet.rows=186')
            ->expectsOutput('metric.teplouchet.duplicate_rows=14')
            ->expectsOutput('metric.teplouchet.safe_unique_offers=172')
            ->expectsOutput('metric.teplouchet.retail_below_purchase=0')
            ->expectsOutput('metric.teplouchet.reference_rows=382')
            ->assertSuccessful();
        $this->assertDatabaseMissing('suppliers', ['code' => 'teplouchet']);

        $this->artisan('supplier:sync-teplouchet', [
            '--apply' => true,
            '--match-existing' => true,
            '--sync-retail-prices' => true,
        ])->assertSuccessful();

        $supplier = Supplier::where('code', 'teplouchet')->firstOrFail();
        $this->assertSame('ООО «ТЕПЛОУЧЁТ»', $supplier->name);
        $this->assertSame(172, $supplier->supplierProducts()->count());
        $this->assertSame(2, CrmContactPoint::where('counterparty_id', $supplier->crm_counterparty_id)->count());

        $link = $supplier->supplierProducts()->where('supplier_article_normalized', '505803001')->firstOrFail();
        $this->assertSame($product->id, $link->product_id);
        $this->assertSame('1255.18', $link->price_byn);
        $this->assertFalse($link->in_stock);
        $this->assertNull($link->stock_quantity);
        $this->assertSame('preorder', $link->stock_status);
        $this->assertSame(10, $link->delivery_days);
        $this->assertSame('2026-10-12', $link->last_stock_synced_at->toDateString());
        $this->assertSame('1443.46', $product->fresh()->price);
        $this->assertSame(Product::AVAILABILITY_CHECK, $product->fresh()->effectiveAvailabilityStatus());
        $this->assertSame('Поставка до 10 рабочих дней', $product->fresh()->publicAvailabilityLabel());
        $this->assertStringContainsString('UPS 25-55-180', collect($product->fresh()->specs)->firstWhere('key', 'Аналоги Grundfos')['value']);
        $this->assertStringContainsString('TOP S 25/5', collect($product->fresh()->specs)->firstWhere('key', 'Аналоги Wilo')['value']);
        $this->assertDatabaseHas('product_analogs', [
            'product_id' => $product->id,
            'analog_product_id' => $wiloAnalog->id,
            'source' => 'teplouchet',
            'match_method' => 'exact_model',
        ]);
        $this->assertDatabaseHas('product_analogs', [
            'product_id' => $referenceOnlyDab->id,
            'analog_product_id' => $referenceOnlyWilo->id,
            'source' => 'teplouchet',
            'match_method' => 'exact_model',
        ]);
        $this->assertSame($wiloAnalog->id, $product->fresh()->analogProducts()->firstOrFail()->id);
        $this->assertSame($product->id, $wiloAnalog->fresh()->analogOfProducts()->firstOrFail()->id);

        $minskLink = $supplier->supplierProducts()->where('supplier_article_normalized', '505812041')->firstOrFail();
        $this->assertSame($minskProduct->id, $minskLink->product_id);
        $this->assertSame('exact_dab_core_model', $minskLink->match_confidence);
        $this->assertTrue($minskLink->in_stock);
        $this->assertSame('always_available', $minskLink->stock_status);
        $this->assertSame('ТЕПЛОУЧЁТ · Минск', $minskLink->warehouse_name);
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $minskProduct->fresh()->effectiveAvailabilityStatus());
        $this->assertSame('В наличии', $minskProduct->fresh()->publicAvailabilityLabel());

        $misbrandedLink = $supplier->supplierProducts()->where('supplier_article_normalized', '505802041')->firstOrFail();
        $this->assertSame($misbrandedProduct->id, $misbrandedLink->product_id);
        $this->assertSame('exact_sku', $misbrandedLink->match_confidence);

        $this->get('/'.$category->slug.'/'.$product->slug)
            ->assertOk()
            ->assertDontSee('Совместимые аналоги');
    }
}

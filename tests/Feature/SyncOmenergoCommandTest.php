<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CrmContactPoint;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\OmenergoPriceList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncOmenergoCommandTest extends TestCase
{
    use RefreshDatabase;

    private function feed(int $count = 150): string
    {
        $f = fopen('php://temp', 'r+');
        fputcsv($f, ['Артикул', 'Категория', 'Наименование каталожное', 'Наименование для высталения счетов', 'Торговая марка', 'Страна', 'Гарантия, м', 'Вес, г', 'Краткие характеристики', 'Комплектация', 'Наличие', 'МРЦ', 'Д2'], ',', '"', '');
        for ($i = 1; $i <= $count; $i++) {
            fputcsv($f, ['Е0101-'.$i, 'однофазные стабилизаторы напряжения', 'Энергия Voltron '.$i.' (HP)', 'Стабилизатор Voltron '.$i, 'Энергия', 'Китай', 36, '3 500', 'Релейный стабилизатор, 5% точность.', 'Коробка, руководство.', '**', '226,35', '188,63'], ',', '"', '');
        }
        rewind($f);

        return stream_get_contents($f);
    }

    public function test_limited_identity_report_contains_only_two_authorized_rows_and_no_prices(): void
    {
        Storage::fake('local');
        Http::fake([OmenergoPriceList::CSV_URL => Http::response($this->feed())]);
        $brand = Brand::create(['name' => 'Энергия', 'slug' => 'energy-review']);
        $category = Category::create(['name' => 'Тест', 'slug' => 'energy-review']);
        foreach ([1, 2] as $i) {
            Product::create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'Энергия Voltron '.$i.' (HP) X', 'slug' => 'review-'.$i]);
        }
        $before = Product::count();
        $path = storage_path('app/reports/omenergo/two-identities.csv');
        try {
            $this->artisan('supplier:sync-omenergo', ['--dry-run' => true, '--review-two' => true])->assertSuccessful();
            $f = fopen($path, 'rb');
            $rows = [];
            while (($record = fgetcsv($f, escape: '')) !== false) {
                $rows[] = $record;
            }
            fclose($f);
            $this->assertCount(3, $rows);
            $this->assertSame(['supplier_article', 'name', 'product_id', 'candidate'], $rows[0]);
            $this->assertSame('Е0101-1', $rows[1][0]);
            $this->assertStringNotContainsString('188', file_get_contents($path));
            $this->assertSame($before, Product::count());
            $this->artisan('supplier:sync-omenergo', ['--apply' => true, '--review-two' => true])->assertFailed();
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_onboarding_is_idempotent_and_daily_sync_does_not_create_cards(): void
    {
        Storage::fake('local');
        Http::fake([OmenergoPriceList::CSV_URL => Http::response($this->feed())]);
        $this->artisan('supplier:sync-omenergo', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Supplier::where('code', 'omenergo')->count());
        $this->artisan('supplier:sync-omenergo', ['--apply' => true])->assertFailed();
        $this->assertSame(0, Supplier::where('code', 'omenergo')->count());
        $this->artisan('supplier:sync-omenergo', ['--apply' => true, '--confirm-pricing' => true, '--create-new' => true, '--update-content' => true])->assertSuccessful();
        $supplier = Supplier::where('code', 'omenergo')->firstOrFail();
        $this->assertSame(150, $supplier->supplierProducts()->count());
        $this->assertSame(4, CrmContactPoint::where('counterparty_id', $supplier->crm_counterparty_id)->count());
        $link = $supplier->supplierProducts()->first();
        $this->assertNull($link->stock_quantity);
        $this->assertSame('188.63', $link->price_byn);
        $this->assertSame('226.35', $link->product->price);
        $this->assertFalse($link->product->is_active);
        $this->assertStringContainsString('Комплект поставки', $link->product->content);
        $before = Product::count();
        $this->artisan('supplier:sync-omenergo', ['--apply' => true])->assertSuccessful();
        $this->assertSame($before, Product::count());
        $this->assertSame(4, CrmContactPoint::where('counterparty_id', $supplier->crm_counterparty_id)->count());
    }

    public function test_bad_or_short_feed_does_not_modify_existing_prices(): void
    {
        Storage::fake('local');
        $before = SupplierProduct::count();
        Http::fake([OmenergoPriceList::CSV_URL => Http::response($this->feed(2))]);
        $this->artisan('supplier:sync-omenergo', ['--apply' => true, '--confirm-pricing' => true, '--create-new' => true])->assertFailed();
        $this->assertSame($before, SupplierProduct::count());
    }

    public function test_conflicting_sheet_power_is_not_published_as_fact(): void
    {
        $csv = str_replace(['Энергия Voltron 1 (HP)', 'Релейный стабилизатор, 5% точность.'], ['Энергия ACH 10000', 'Релейный стабилизатор, 8000 ВА, 5% точность.'], $this->feed(1));
        $row = (new OmenergoPriceList)->parse($csv)[0];
        $this->assertTrue($row['technical_conflict']);
        $this->assertStringNotContainsString('8000', (new OmenergoPriceList)->content($row));
    }

    public function test_documented_model_alias_links_existing_card_instead_of_creating_duplicate(): void
    {
        Storage::fake('local');
        $brand = Brand::firstOrCreate(['name' => 'Энергия'], ['slug' => 'energy-test', 'is_active' => true]);
        $category = Category::firstOrCreate(['slug' => 'energy-test'], ['name' => 'Тест стабилизаторов']);
        $existing = Product::create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'Стабилизатор напряжения Энергия Voltron РСН-1', 'sku' => 'KOTLOV-ENERGY-TEST', 'slug' => 'existing-voltron-test']);
        Http::fake([OmenergoPriceList::CSV_URL => Http::response($this->feed())]);
        $this->artisan('supplier:sync-omenergo', ['--apply' => true, '--confirm-pricing' => true, '--create-new' => true])->assertSuccessful();
        $link = SupplierProduct::where('supplier_article_normalized', 'E0101-1')->firstOrFail();
        $this->assertSame($existing->id, $link->product_id);
        $this->assertDatabaseMissing('products', ['sku' => 'OMENERGO-E0101-1']);
    }
}

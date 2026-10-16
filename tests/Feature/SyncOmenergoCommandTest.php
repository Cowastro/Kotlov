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

    public function test_audit_includes_unlinked_legacy_cards_outside_supplier_brand_and_category_ids(): void
    {
        Http::fake([OmenergoPriceList::CSV_URL => Http::response($this->feed())]);
        $category = Category::create(['name' => 'Другая категория', 'slug' => 'legacy-audit']);
        foreach ([['Энергия APC-500', true, false], ['Энерготех OLD', false, false], ['Энергия APC-1000', true, true], ['Другой бренд', true, false]] as $i => [$name, $active, $archived]) {
            Product::create(['category_id' => $category->id, 'name' => $name, 'slug' => 'audit-'.$i, 'is_active' => $active, 'is_archived' => $archived, 'price' => 140]);
        }
        $this->artisan('supplier:sync-omenergo', ['--dry-run' => true])
            ->expectsOutput('metric.omenergo.catalog_cards=3')
            ->expectsOutput('metric.omenergo.catalog_unlinked_active=1')
            ->expectsOutput('metric.omenergo.catalog_unlinked_hidden=1')
            ->expectsOutput('metric.omenergo.catalog_unlinked_archived=1')
            ->assertSuccessful();
        $this->assertSame(4, Product::where('price', 140)->count());
    }

    public function test_apc_restore_preserves_original_identity_and_moves_exact_supplier_link_only(): void
    {
        Storage::fake('local');
        $csv = str_replace(['Е0101-1,', 'Энергия Voltron 1 (HP)'], ['Е0101-0131,', 'Энергия APC 500'], $this->feed());
        Http::fake([OmenergoPriceList::CSV_URL => Http::response($csv)]);
        $supplier = Supplier::create(['name' => 'ОмЭнерго', 'code' => 'omenergo', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Энергия', 'slug' => 'apc-restore']);
        $category = Category::create(['name' => 'Стабилизаторы', 'slug' => 'apc-restore']);
        $path = 'img/products/omenergo/apc-restore-test.png';
        $file = public_path($path);
        $this->assertFileDoesNotExist($file);
        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }
        $chunk = fn ($type, $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        file_put_contents($file, "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 220, 220, 8, 2, 0, 0, 0)).$chunk('IDAT', gzcompress(str_repeat("\0".str_repeat("\0\0\0", 220), 220))).$chunk('IEND', ''));
        try {
            $base = ['category_id' => $category->id, 'brand_id' => $brand->id, 'in_stock' => false, 'stock_qty' => null];
            $old = Product::create($base + ['name' => 'Стабилизатор напряжения Энергия АРС-500', 'sku' => 'PS-007.028', 'slug' => 'original-apc', 'price' => 140, 'is_archived' => true, 'views_count' => 77]);
            $new = Product::create($base + ['name' => 'Стабилизатор напряжения Энергия APC 500', 'sku' => 'OMENERGO-E0101-0131', 'slug' => 'new-apc', 'price' => 226.35, 'is_active' => true, 'images' => [$path], 'specs' => array_fill(0, 8, ['key' => 'Параметр', 'value' => '500'])]);
            $link = SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $new->id, 'supplier_article' => 'Е0101-0131', 'supplier_article_normalized' => 'E0101-0131', 'price_byn' => 188.63, 'currency' => 'BYN']);
            $this->artisan('supplier:sync-omenergo', ['--restore-selling-apc' => true, '--dry-run' => true])->assertSuccessful();
            $this->assertTrue($old->fresh()->is_archived);
            $new->update(['price' => 140]);
            $this->artisan('supplier:sync-omenergo', ['--restore-selling-apc' => true, '--apply' => true])->assertFailed();
            $this->assertSame($new->id, $link->fresh()->product_id);
            $new->update(['price' => 226.35]);
            $this->artisan('supplier:sync-omenergo', ['--restore-selling-apc' => true, '--apply' => true])->assertSuccessful();
            $this->assertSame($old->id, $link->fresh()->product_id);
            $this->assertSame('PS-007.028', $old->fresh()->sku);
            $this->assertSame('original-apc', $old->fresh()->slug);
            $this->assertSame(77, $old->fresh()->views_count);
            $this->assertSame('226.35', $old->fresh()->price);
            $this->assertSame([$path], $old->fresh()->images);
            $this->assertTrue($old->fresh()->is_featured);
            $this->assertFalse($old->fresh()->is_archived);
            $this->assertTrue($new->fresh()->is_archived);
            $this->assertNotEmpty(Storage::disk('local')->allFiles('omenergo/restore-review'));
            $this->artisan('supplier:sync-omenergo', ['--restore-selling-apc' => true, '--apply' => true])->assertSuccessful();
            $legacy = Product::create($base + ['name' => 'Энергия Voltron 2 (5%)', 'sku' => 'LEGACY-2', 'slug' => 'legacy-original', 'price' => 140, 'is_archived' => true, 'is_featured' => true, 'views_count' => 42]);
            $duplicate = Product::create($base + ['name' => 'Энергия Voltron 2 (HP)', 'sku' => 'OMENERGO-E0101-2', 'slug' => 'legacy-new', 'price' => 226.35, 'is_active' => true, 'images' => [$path], 'specs' => $new->specs]);
            $legacyLink = SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $duplicate->id, 'supplier_article' => 'Е0101-2', 'supplier_article_normalized' => 'E0101-2', 'price_byn' => 188.63]);
            $legacy->update(['manual_stock_management' => true]);
            $this->artisan('supplier:sync-omenergo', ['--restore-matching-legacy' => true, '--apply' => true])
                ->expectsOutput('metric.omenergo.legacy_restore_protected=1')->assertSuccessful();
            $this->assertSame($duplicate->id, $legacyLink->fresh()->product_id);
            $legacy->update(['manual_stock_management' => false]);
            $ambiguous = Product::create($base + ['name' => $legacy->name, 'slug' => 'ambiguous-legacy', 'is_archived' => true]);
            $this->artisan('supplier:sync-omenergo', ['--restore-matching-legacy' => true, '--apply' => true])
                ->expectsOutput('metric.omenergo.legacy_restore_ambiguous=1')->assertSuccessful();
            $this->assertSame($duplicate->id, $legacyLink->fresh()->product_id);
            $ambiguous->update(['name' => 'Энергия Voltron Neo 2']);
            $this->artisan('supplier:sync-omenergo', ['--restore-matching-legacy' => true, '--dry-run' => true])
                ->expectsOutput('metric.omenergo.legacy_restore_candidates=1')->assertSuccessful();
            $this->assertTrue($legacy->fresh()->is_archived);
            $this->artisan('supplier:sync-omenergo', ['--restore-matching-legacy' => true, '--apply' => true])->assertSuccessful();
            $this->assertSame($legacy->id, $legacyLink->fresh()->product_id);
            $this->assertSame('legacy-original', $legacy->fresh()->slug);
            $this->assertSame(42, $legacy->fresh()->views_count);
            $this->assertTrue($legacy->fresh()->is_featured);
            $this->assertFalse($legacy->fresh()->is_archived);
            $this->assertSame('226.35', $legacy->fresh()->price);
            $this->assertTrue($duplicate->fresh()->is_archived);
            $this->assertDatabaseHas('redirects', ['from_url' => '/apc-restore/legacy-new', 'to_url' => '/apc-restore/legacy-original', 'status_code' => 301]);
            $this->get('/apc-restore/legacy-new')->assertRedirect('/apc-restore/legacy-original');
            $this->artisan('supplier:sync-omenergo', ['--apply' => true, '--confirm-pricing' => true])->assertSuccessful();
            $this->assertSame($legacy->id, $legacyLink->fresh()->product_id);
            $this->assertTrue($legacy->fresh()->is_featured);
            $this->artisan('supplier:sync-omenergo', ['--restore-matching-legacy' => true, '--apply' => true])
                ->expectsOutput('metric.omenergo.legacy_restored=0')->assertSuccessful();
        } finally {
            unlink($file);
        }
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
        $existing = Product::create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'Стабилизатор напряжения Энергия Voltron 1 (5%)', 'sku' => 'KOTLOV-ENERGY-TEST', 'slug' => 'existing-voltron-test']);
        Http::fake([OmenergoPriceList::CSV_URL => Http::response($this->feed())]);
        $this->artisan('supplier:sync-omenergo', ['--apply' => true, '--confirm-pricing' => true, '--create-new' => true])->assertSuccessful();
        $link = SupplierProduct::where('supplier_article_normalized', 'E0101-1')->firstOrFail();
        $this->assertSame($existing->id, $link->product_id);
        $this->assertDatabaseMissing('products', ['sku' => 'OMENERGO-E0101-1']);
    }

    public function test_distinct_series_do_not_block_new_cards_or_reuse_old_products(): void
    {
        Storage::fake('local');
        $brand = Brand::firstOrCreate(['name' => 'Энергия'], ['slug' => 'energy-series']);
        $category = Category::create(['name' => 'Тест серий', 'slug' => 'energy-series']);
        $neo = Product::create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'Стабилизатор напряжения Энергия Voltron Neo 3', 'slug' => 'old-neo']);
        $apc = Product::create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'Стабилизатор напряжения Энергия АРС-1000', 'slug' => 'old-apc']);
        $csv = str_replace('Энергия Voltron 1 (HP)', 'Энергия Люкс 1000', $this->feed());
        Http::fake([OmenergoPriceList::CSV_URL => Http::response($csv)]);
        $this->artisan('supplier:sync-omenergo', ['--apply' => true, '--confirm-pricing' => true, '--create-new' => true])->assertSuccessful();
        $this->assertDatabaseHas('products', ['sku' => 'OMENERGO-E0101-1']);
        $this->assertDatabaseHas('products', ['sku' => 'OMENERGO-E0101-3']);
        $this->assertDatabaseMissing('supplier_products', ['product_id' => $neo->id]);
        $this->assertDatabaseMissing('supplier_products', ['product_id' => $apc->id]);
        $this->assertFalse($neo->fresh()->is_archived);
        $this->assertFalse($apc->fresh()->is_archived);
    }
}

<?php

namespace Tests\Feature;

use App\Services\PriceList\SvahGroupPriceList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncSvahGroupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_reads_every_supplier_brand(): void
    {
        $path = $this->workbook([
            ['Твердотопливные котлы', null, null, null],
            ['Татра КТ15-НП1 Сталь 6мм правый одноконтурный', 2039.63, 1, 2719.50],
            ['Печи банные', 'Скидка 20%', null, null],
            ['Печь банная Татра Т16 с баком 80л', 1185.41, 2, 1481.76],
            ['Печь банная SVAH Т16 сталь 8мм', 514.50, 10, 791.54],
            ['Печь банная HOT T20', 535.50, 0, 823.85],
        ]);

        $rows = app(SvahGroupPriceList::class)->parse($path);

        $this->assertCount(4, $rows);
        $this->assertSame('TATRA-KT15-NP1', $rows[0]['article']);
        $this->assertSame('tatra:boiler:kt15-np1', $rows[0]['signature']);
        $this->assertSame('TATRA-T16-TANK80', $rows[1]['article']);
        $this->assertSame('Печи банные', $rows[1]['section']);
        $this->assertSame(2, $rows[1]['stock_quantity']);
        $this->assertSame(1185.41, $rows[1]['partner_price']);
        $this->assertSame(1481.76, $rows[1]['retail_price']);
        $this->assertSame('SVAH-T16', $rows[2]['article']);
        $this->assertSame('svah:stove:t16', $rows[2]['signature']);
        $this->assertSame('HOT-T20', $rows[3]['article']);
        $this->assertSame(0, $rows[3]['stock_quantity']);
    }

    public function test_apply_links_existing_products_and_updates_partner_price_stock_and_retail(): void
    {
        $brandId = DB::table('brands')->insertGetId([
            'name' => 'Tatra',
            'slug' => 'tatra',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Твердотопливные котлы',
            'slug' => 'tverdotoplivnye',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $saunaCategoryId = DB::table('categories')->insertGetId([
            'name' => 'Дровяные печи для бани',
            'slug' => 'drovyanye-pechi-dlya-bani',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $boilerId = $this->product($categoryId, $brandId, 'KOTLOV-TATRA-1', 'Котел Татра КТ15-НП1', 2500);
        $stoveId = $this->product($categoryId, $brandId, 'KOTLOV-TATRA-2', 'Печь банная Tatra T16 с баком 80 л', 1300);
        $siteOnlyId = $this->product($categoryId, $brandId, 'KOTLOV-TATRA-3', 'Печь банная Tatra T24', 1400);

        $path = $this->workbook([
            ['Твердотопливные котлы', null, null, null],
            ['Татра КТ15-НП1 Сталь 6мм правый одноконтурный', 2039.63, 1, 2719.50],
            ['Печи банные', 'Скидка 20%', null, null],
            ['Печь банная Татра Т16 с баком 80л', 1185.41, 2, 1481.76],
            ['Печь банная SVAH Т16 сталь 8мм', 514.50, 10, 791.54],
            ['Печь банная HOT T20', 535.50, 0, 823.85],
        ]);
        $report = storage_path('framework/testing/svah-group-audit.csv');

        $this->artisan('supplier:sync-svah-group', [
            '--price-file' => $path,
            '--dry-run' => true,
            '--report' => $report,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('suppliers', ['code' => 'svah_group']);
        $this->assertStringContainsString('site_only', (string) file_get_contents($report));

        $this->artisan('supplier:sync-svah-group', [
            '--price-file' => $path,
            '--apply' => true,
            '--sync-retail-prices' => true,
            '--report' => $report,
        ])->assertSuccessful();

        $supplierId = (int) DB::table('suppliers')->where('code', 'svah_group')->value('id');
        $this->assertGreaterThan(0, $supplierId);
        $this->assertDatabaseHas('supplier_syncs', [
            'key' => 'svah_group_price_stock',
            'command' => 'supplier:sync-svah-group',
        ]);
        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplierId,
            'product_id' => $boilerId,
            'supplier_article' => 'TATRA-KT15-NP1',
            'price_byn' => 2039.63,
            'stock_quantity' => 1,
            'in_stock' => true,
        ]);
        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplierId,
            'product_id' => $stoveId,
            'supplier_article' => 'TATRA-T16-TANK80',
            'price_byn' => 1185.41,
            'stock_quantity' => 2,
        ]);
        $this->assertSame(2719.50, (float) DB::table('products')->where('id', $boilerId)->value('price'));
        $this->assertSame(1481.76, (float) DB::table('products')->where('id', $stoveId)->value('price'));
        $svahProduct = DB::table('products')->where('name', 'Печь банная SVAH Т16 сталь 8мм')->first();
        $hotProduct = DB::table('products')->where('name', 'Печь банная HOT T20')->first();
        $this->assertNotNull($svahProduct);
        $this->assertNotNull($hotProduct);
        $this->assertFalse((bool) $svahProduct->is_active);
        $this->assertFalse((bool) $hotProduct->is_active);
        $this->assertSame($saunaCategoryId, (int) $svahProduct->category_id);
        $this->assertSame(791.54, (float) $svahProduct->price);
        $this->assertSame(823.85, (float) $hotProduct->price);
        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplierId,
            'product_id' => $svahProduct->id,
            'supplier_article' => 'SVAH-T16',
            'price_byn' => 514.50,
            'stock_quantity' => 10,
            'in_stock' => true,
        ]);
        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplierId,
            'product_id' => $hotProduct->id,
            'supplier_article' => 'HOT-T20',
            'price_byn' => 535.50,
            'stock_quantity' => 0,
            'in_stock' => false,
        ]);
        $this->assertStringContainsString('site_only', (string) file_get_contents($report));
        $this->assertStringContainsString('KOTLOV-TATRA-3', (string) file_get_contents($report));

        $nextPath = $this->workbook([
            ['Печи банные', 'Скидка 20%', null, null],
            ['Печь банная Татра Т16 с баком 80л', 1200, 0, 1500],
        ]);
        $exitCode = Artisan::call('supplier:sync-svah-group', [
            '--price-file' => $nextPath,
            '--apply' => true,
        ]);
        $this->assertSame(0, $exitCode, Artisan::output());

        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplierId,
            'product_id' => $boilerId,
            'stock_quantity' => 0,
            'in_stock' => false,
            'stock_status' => 'out_of_stock',
        ]);
        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplierId,
            'product_id' => $stoveId,
            'price_byn' => 1200,
            'stock_quantity' => 0,
            'in_stock' => false,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $siteOnlyId,
            'price' => 1400,
        ]);
    }

    private function product(int $categoryId, int $brandId, string $sku, string $name, float $price): int
    {
        return (int) DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => $name,
            'slug' => mb_strtolower(str_replace(' ', '-', $sku)),
            'sku' => $sku,
            'price' => $price,
            'currency' => 'BYN',
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
            'availability_status' => 'check',
            'stock_qty' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function workbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Наименование ', 'Скидка 25 %', 'Остаток на складе', 'Цена  отпускная'],
            ...$rows,
        ], null, 'A1', true);

        $path = storage_path('framework/testing/svah-'.uniqid('', true).'.xlsx');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}

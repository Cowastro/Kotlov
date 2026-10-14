<?php

namespace Tests\Feature;

use App\Services\PriceList\SvahGroupPriceList;
use App\Services\PriceList\SvahGroupCatalogContent;
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
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplierId,
            'contact' => 'Виталий · +375 29 601-49-89',
        ]);
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
        $tatraProduct = DB::table('products')->where('id', $stoveId)->firstOrFail();
        $this->assertSame('Tatra T16 Банная печь с навесным баком 80 л', $tatraProduct->name);
        $this->assertTrue((bool) $tatraProduct->is_active);
        $this->assertFalse((bool) $tatraProduct->requires_price_confirmation);
        $this->assertStringContainsString('четырёхходовой теплообменник', $tatraProduct->content);
        $this->assertSame(
            ['img/products/tatra/tatra-t16-water-tank-80l-clean.png'],
            json_decode($tatraProduct->images, true, 512, JSON_THROW_ON_ERROR),
        );
        $tatraSpecs = collect(json_decode($tatraProduct->specs, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame('80 л', $tatraSpecs->firstWhere('key', 'Объём бака')['value']);
        $this->assertSame('150 мм', $tatraSpecs->firstWhere('key', 'Диаметр дымохода')['value']);
        $this->assertGreaterThanOrEqual(20, $tatraSpecs->count());
        $this->assertFileExists(public_path('img/products/tatra/tatra-t16-water-tank-80l-clean.png'));
        $svahProductId = DB::table('supplier_products')->where('supplier_article', 'SVAH-T16')->value('product_id');
        $hotProductId = DB::table('supplier_products')->where('supplier_article', 'HOT-T20')->value('product_id');
        $svahProduct = DB::table('products')->where('id', $svahProductId)->first();
        $hotProduct = DB::table('products')->where('id', $hotProductId)->first();
        $this->assertNotNull($svahProduct);
        $this->assertNotNull($hotProduct);
        $this->assertTrue((bool) $svahProduct->is_active);
        $this->assertFalse((bool) $hotProduct->is_active);
        $this->assertNotEmpty($svahProduct->short_description);
        $this->assertNotEmpty($hotProduct->short_description);
        $this->assertGreaterThanOrEqual(15, count(json_decode($svahProduct->specs, true, 512, JSON_THROW_ON_ERROR)));
        $this->assertGreaterThanOrEqual(15, count(json_decode($hotProduct->specs, true, 512, JSON_THROW_ON_ERROR)));
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

    public function test_it_creates_the_current_tatra_t16_card_with_complete_catalog_content_and_supplier_contact(): void
    {
        DB::table('categories')->insert([
            'name' => 'Дровяные печи для бани',
            'slug' => 'drovyanye-pechi-dlya-bani',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $path = $this->workbook([
            ['Печи банные', 'Скидка 20%', null, null],
            ['Печь банная Татра Т16 с баком 80л', 1200, 0, 1500],
        ]);

        $this->artisan('supplier:sync-svah-group', [
            '--price-file' => $path,
            '--apply' => true,
            '--sync-retail-prices' => true,
        ])->expectsOutputToContain('metric.svah_group.content_ready=1')
            ->expectsOutputToContain('metric.svah_group.content_missing=0')
            ->assertSuccessful();

        $product = DB::table('products')->where('name', 'Tatra T16 Банная печь с навесным баком 80 л')->sole();
        $this->assertSame(1500.00, (float) $product->price);
        $this->assertTrue((bool) $product->is_active);
        $this->assertFalse((bool) $product->in_stock);
        $this->assertSame('https://tatra1.by/p332449-pech-dlya-bani.html', $product->content_source_url);
        $this->assertNotEmpty($product->short_description);
        $this->assertNotEmpty($product->content);
        $this->assertCount(1, json_decode($product->images, true, 512, JSON_THROW_ON_ERROR));
        $this->assertGreaterThanOrEqual(20, count(json_decode($product->specs, true, 512, JSON_THROW_ON_ERROR)));
        $this->assertDatabaseHas('suppliers', [
            'code' => 'svah_group',
            'contact' => 'Виталий · +375 29 601-49-89',
        ]);
        $this->assertDatabaseHas('supplier_products', [
            'supplier_article' => 'TATRA-T16-TANK80',
            'product_id' => $product->id,
            'price_byn' => 1200,
            'stock_quantity' => 0,
            'in_stock' => false,
        ]);
    }

    public function test_catalog_content_covers_every_current_supplier_price_signature_without_blank_specs(): void
    {
        $signatures = [
            'tatra:boiler:kt15-np1', 'tatra:boiler:kt15-nl1',
            'tatra:boiler:kt15-np2', 'tatra:boiler:kt15-nl2',
            'tatra:boiler:kt30-np1', 'tatra:boiler:kt30-nl1',
            'tatra:boiler:kt30-np2', 'tatra:boiler:kt30-nl2',
            'tatra:boiler:kt40-np1', 'tatra:boiler:kt40-nl1',
            'tatra:boiler:kt50-np1', 'tatra:boiler:kt50-nl1',
            'tatra:boiler:kt50', 'tatra:boiler:kt70-n',
            'tatra:boiler:kt95', 'tatra:boiler:kt95-n',
            'tatra:stove:t10', 'tatra:stove:t10:tank50',
            'tatra:stove:t16', 'tatra:stove:t16:tank80',
            'tatra:stove:t24', 'tatra:stove:t24:tank80',
            'tatra:stove:t30', 'tatra:stove:t30:tank100',
            'tatra:stove:t32:tank100', 'tatra:stove:t32t', 'tatra:stove:t36',
            'svah:stove:t16', 'svah:stove:t20', 'svah:stove:t24',
            'hot:stove:t16', 'hot:stove:t20', 'hot:stove:t24',
        ];
        $catalog = app(SvahGroupCatalogContent::class);

        foreach ($signatures as $signature) {
            $profile = $catalog->profile(['signature' => $signature]);

            $this->assertNotNull($profile, $signature);
            $this->assertNotEmpty($profile['name'], $signature);
            $this->assertNotEmpty($profile['short_description'], $signature);
            $this->assertNotEmpty($profile['content'], $signature);
            $this->assertNotEmpty($profile['source_url'], $signature);
            $this->assertGreaterThanOrEqual(15, count($profile['specs']), $signature);
            if (! str_starts_with($signature, 'hot:')) {
                $this->assertNotEmpty($profile['images'], $signature);
            }
            foreach ($profile['specs'] as $spec) {
                $this->assertNotSame('', trim((string) $spec['key']), $signature);
                $this->assertNotSame('', trim((string) $spec['value']), $signature);
            }
        }
    }

    public function test_every_current_tatra_and_svah_profile_has_a_verified_catalog_image(): void
    {
        $catalog = app(SvahGroupCatalogContent::class);
        $signatures = [
            'tatra:boiler:kt15-np1', 'tatra:boiler:kt15-np2',
            'tatra:boiler:kt30-np1', 'tatra:boiler:kt30-np2',
            'tatra:boiler:kt40-np1', 'tatra:boiler:kt50',
            'tatra:boiler:kt70-n', 'tatra:boiler:kt95-n',
            'tatra:stove:t10', 'tatra:stove:t10:tank50',
            'tatra:stove:t16', 'tatra:stove:t16:tank80',
            'tatra:stove:t24', 'tatra:stove:t24:tank80',
            'tatra:stove:t30', 'tatra:stove:t30:tank100',
            'tatra:stove:t32:tank100', 'tatra:stove:t32t', 'tatra:stove:t36',
            'svah:stove:t16', 'svah:stove:t20', 'svah:stove:t24',
        ];

        foreach ($signatures as $signature) {
            $profile = $catalog->profile(['signature' => $signature]);

            $this->assertNotNull($profile, $signature);
            $this->assertCount(1, $profile['images'], $signature);
            $this->assertMatchesRegularExpression(
                '#^(?:https://|img/products/)#',
                $profile['images'][0],
                $signature,
            );
        }
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

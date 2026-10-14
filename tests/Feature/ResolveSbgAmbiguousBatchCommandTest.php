<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResolveSbgAmbiguousBatchCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_links_an_unmatched_tsar_pechi_card_without_overwriting_its_retail_price(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-tsar-pechi',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Печи для бани',
            'slug' => 'bath-stoves-tsar-pechi-test',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-009.954',
            'name' => 'Печь-каменка Царь-Печи Святогор (8 мм)',
            'slug' => 'tsar-pechi-svyatogor-test',
            'price' => 1566,
            'in_stock' => false,
            'stock_qty' => null,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'tsar-pechi-svyatogor',
            'external_code' => 'УТ-00000080',
            'name' => 'Банная печь "Святогор" (8мм)',
            'price_byn' => 1253,
            'stock_quantity' => 2,
            'match_status' => 'unmatched',
            'candidates' => [],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261014-r',
            '--code' => ['УТ-00000080'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product->refresh();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame('1566.00', $product->price);
        $this->assertTrue($product->in_stock);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'УТ-00000080',
            'product_id' => $product->id,
            'confidence' => 'manual',
        ]);
    }

    public function test_it_links_the_unmatched_darco_szk130_shutter_without_overwriting_retail(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-darco-szk130',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Шиберы дымохода',
            'slug' => 'darco-shutters-test',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-009.397',
            'name' => 'Шибер Darco SZK130-CZ2(ML) короткая ручка',
            'slug' => 'darco-szk130-cz2-ml-test',
            'price' => 127,
            'in_stock' => false,
            'stock_qty' => null,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'af1f3529-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000029',
            'name' => 'Шибер каминный SZK 130-CZ2 (ML)',
            'price_byn' => 67.99,
            'stock_quantity' => 1,
            'match_status' => 'unmatched',
            'candidates' => [],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261014-s',
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product->refresh();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame('127.00', $product->price);
        $this->assertTrue($product->in_stock);
        $this->assertSame('1', (string) $product->stock_qty);
    }

    public function test_it_dry_runs_and_applies_only_a_still_current_exact_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
        ]);
        $category = Category::query()->create([
            'name' => 'Дымоходы',
            'slug' => 'chimneys',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-007963',
            'name' => 'Теплов и Сухов Отвод 45* ОМ-Р 430-0.5 D80 (2S)',
            'slug' => 'exact-sbg-product',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'exact-sbg-item',
            'external_code' => 'УТ-00000567',
            'external_sku' => 'TS.FRT.O45.0080.83624',
            'name' => 'Отвод 45* ОМ-Р 430-0.5 D80 (2S) (У)',
            'stock_quantity' => 4,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $product->id, 'score' => 0.71]],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', ['--code' => ['УТ-00000567']])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:resolve-sbg-ambiguous', ['--code' => ['УТ-00000567'], '--apply' => true])
            ->expectsOutputToContain('APPLY')
            ->assertSuccessful();

        $item->refresh();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_candidate', $item->match_method);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'TS.FRT.O45.0080.83624',
            'product_id' => $product->id,
            'confidence' => 'manual',
        ]);
    }

    public function test_it_refuses_a_candidate_when_the_identity_guard_changed(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
        ]);
        $category = Category::query()->create([
            'name' => 'Дымоходы',
            'slug' => 'chimneys',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-007963',
            'name' => 'Теплов и Сухов Отвод 45* ОМ-Р 430-0.5 D100 (2S)',
            'slug' => 'wrong-diameter-product',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'changed-sbg-item',
            'external_code' => 'УТ-00000567',
            'name' => 'Отвод 45* ОМ-Р 430-0.5 D80 (2S) (У)',
            'stock_quantity' => 4,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $product->id, 'score' => 0.71]],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', ['--code' => ['УТ-00000567'], '--apply' => true])
            ->expectsOutputToContain('identity guard no longer matches')
            ->assertFailed();

        $this->assertNull($item->fresh()->product_id);
    }

    public function test_it_links_the_exact_meta_bel_ritm_card_found_outside_the_wrong_candidate_set(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-ritm',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Печи-камины',
            'slug' => 'pechi-kaminy',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-007.486',
            'name' => 'Печь-камин Мета-Бел Ритм АОТ-7',
            'slug' => 'pech-kamin-meta-bel-ritm-aot-7',
            'price' => 1800,
            'in_stock' => false,
            'stock_qty' => null,
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-006.589',
            'name' => 'Печь-каменка Мета-Бел ПБМ 16 (в модификации ПС)',
            'slug' => 'meta-bel-pbm-16-ps',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'meta-bel-ritm-aot-7',
            'external_code' => 'УТ-00001564',
            'name' => 'Печь-камин АОТ 7.0 (в модификации "Ритм")',
            'price_byn' => 1116.90,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.731]],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261012-e',
            '--code' => ['УТ-00001564'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product->refresh();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame('1800.00', $product->price);
        $this->assertTrue($product->in_stock);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'УТ-00001564',
            'product_id' => $product->id,
            'confidence' => 'manual',
        ]);
    }

    public function test_it_links_the_exact_insulated_ceiling_passage_found_outside_the_wrong_candidate_set(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-ppu-u',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Крепления и монтаж',
            'slug' => 'chimney-mounting',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-011.626',
            'name' => 'Проход перекрытия универсальный утепленный ППУ-У',
            'slug' => 'ppu-u-590-180-210',
            'price' => 189.05,
            'in_stock' => false,
            'stock_qty' => null,
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-009.106',
            'name' => 'Проход перекрытия универсальный ППУ-Р 500х500 Ø 180-210',
            'slug' => 'ppu-r-500-180-210',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '2300b736-ae85-11ed-a6b6-1c1b0d1de7c2',
            'external_code' => 'УТ-00007867',
            'external_sku' => 'TS.KMP.PPU.0210.81596',
            'name' => 'Проход перекрытия универсальный утепленный ППУ-У 590х590 D180-210 (круг) (М)',
            'price_byn' => 155.69,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.7778]],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261012-f',
            '--code' => ['УТ-00007867'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product->refresh();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame('189.05', $product->price);
        $this->assertTrue($product->in_stock);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'TS.KMP.PPU.0210.81596',
            'product_id' => $product->id,
            'confidence' => 'manual',
        ]);
    }

    public function test_it_links_electrovel_48m_to_the_existing_hotta_eko_line_card(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-electrovel-48m',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Электрические котлы',
            'slug' => 'electric-boilers',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-008.857',
            'name' => 'Электрокотел KOTLOV GE Eko 48М',
            'slug' => 'electrovel-hotta-eko-48m',
            'price' => 600,
            'in_stock' => false,
            'stock_qty' => null,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'b7d51899-1d6e-11ea-a220-1c1b0d1de7c2',
            'external_code' => 'УТ-00003031',
            'name' => 'Электрокотел "ElectroVel" 48М НВ (клавиши)',
            'price_byn' => 486,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $product->id, 'score' => 0.6602]],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261012-g',
            '--code' => ['УТ-00003031'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product->refresh();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_candidate', $item->match_method);
        $this->assertSame('600.00', $product->price);
        $this->assertTrue($product->in_stock);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'УТ-00003031',
            'product_id' => $product->id,
            'confidence' => 'manual',
        ]);
    }

    public function test_it_links_the_18m_and_15m_electrovel_variants_to_their_exact_hotta_eko_cards(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-electrovel-midrange',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Электрические котлы',
            'slug' => 'electric-boilers-midrange',
            'parent_id' => 0,
        ]);
        $product18 = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-008.853',
            'name' => 'Электрокотел KOTLOV GE Eko 18М',
            'slug' => 'electrovel-hotta-eko-18m',
            'price' => 400,
            'in_stock' => false,
            'stock_qty' => null,
        ]);
        $product15 = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-008.852',
            'name' => 'Электрокотел KOTLOV GE Eko 15М',
            'slug' => 'electrovel-hotta-eko-15m',
            'price' => 250,
            'in_stock' => false,
            'stock_qty' => null,
        ]);
        $item18 = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'b7d51895-1d6e-11ea-a220-1c1b0d1de7c2',
            'external_code' => 'УТ-00003027',
            'name' => 'Электрокотел "ElectroVel" 18М НВ (клавиши)',
            'price_byn' => 313,
            'stock_quantity' => 3,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $product18->id, 'score' => 0.6602]],
        ]);
        $item15 = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'b7d51894-1d6e-11ea-a220-1c1b0d1de7c2',
            'external_code' => 'УТ-00003026',
            'name' => 'Электрокотел "ElectroVel" 15М НВ (Авт.)',
            'price_byn' => 213,
            'stock_quantity' => 5,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $product15->id, 'score' => 0.7158]],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261012-h',
            '--code' => ['УТ-00003027', 'УТ-00003026'],
            '--apply' => true,
        ])->assertSuccessful();

        $item18->refresh();
        $item15->refresh();
        $product18->refresh();
        $product15->refresh();
        $this->assertSame($product18->id, $item18->product_id);
        $this->assertSame($product15->id, $item15->product_id);
        $this->assertSame('manual_candidate', $item18->match_method);
        $this->assertSame('manual_candidate', $item15->match_method);
        $this->assertSame('400.00', $product18->price);
        $this->assertSame('250.00', $product15->price);
        $this->assertSame('3', (string) $product18->stock_qty);
        $this->assertSame('5', (string) $product15->stock_qty);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'УТ-00003027',
            'product_id' => $product18->id,
            'confidence' => 'manual',
        ]);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'УТ-00003026',
            'product_id' => $product15->id,
            'confidence' => 'manual',
        ]);
    }

    public function test_it_links_unipump_upc_25_60_to_the_exact_existing_card_outside_the_wrong_apc_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unipump-upc-25-60',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Циркуляционные насосы',
            'slug' => 'circulation-pumps',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-004.660',
            'name' => 'Насос Unipump UPC 25-60 180',
            'slug' => 'unipump-upc-25-60-180',
            'price' => 186.39,
            'in_stock' => false,
            'stock_qty' => null,
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-008.458',
            'name' => 'Насос циркуляционный APC GR 25-60 180',
            'slug' => 'apc-gr-25-60-180',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'unipump-upc-25-60-180',
            'external_code' => 'УТ-00000524',
            'name' => 'Насос циркуляц. (отопл.) UPC 25-60 180',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.83]],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261013-i',
            '--code' => ['УТ-00000524'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product->refresh();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame('186.39', $product->price);
        $this->assertTrue($product->in_stock);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'УТ-00000524',
            'product_id' => $product->id,
            'confidence' => 'manual',
        ]);
    }

    public function test_it_links_unipump_upc_25_40_to_the_researched_canonical_card(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unipump-upc-25-40',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Циркуляционные насосы',
            'slug' => 'circulation-pumps-upc-25-40',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-008.471',
            'name' => 'Насос циркуляционный UNIPUMP UPC 25-40 180',
            'slug' => 'unipump-upc-25-40-180-canonical',
            'price' => 176.92,
            'in_stock' => false,
            'stock_qty' => null,
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-008.469',
            'name' => 'Насос циркуляционный APC GR 25-40 180',
            'slug' => 'apc-gr-25-40-180',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'unipump-upc-25-40-180',
            'external_code' => 'УТ-00000523',
            'name' => 'Насос циркуляц. (отопл.) UPC 25-40 180',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.83]],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261013-j',
            '--code' => ['УТ-00000523'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product->refresh();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame('176.92', $product->price);
        $this->assertTrue($product->in_stock);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'УТ-00000523',
            'product_id' => $product->id,
            'confidence' => 'manual',
        ]);
    }

    public function test_it_links_the_exact_solpi_slp_500ba_euro_card(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-solpi-slp-500ba-euro',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Стабилизаторы напряжения',
            'slug' => 'stabilizatory-napryazheniya-test',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-002.366',
            'name' => 'Стабилизатор напряжения Solpi-M SLP-500BA EURO, 500 ВА, 2 розетки',
            'slug' => 'solpi-m-slp-500ba',
            'price' => 180,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'solpi-slp-500ba-euro',
            'external_code' => 'УТ-00009655',
            'name' => 'Стабилизатор напряжения Solpi-m SLP-500BА EURO',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $product->id, 'score' => 0.9394]],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261013-k',
            '--code' => ['УТ-00009655'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_candidate', $item->match_method);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'УТ-00009655',
            'product_id' => $product->id,
            'confidence' => 'manual',
        ]);
    }

    public function test_it_links_and_refreshes_the_exact_kpd_black_l250_d150_pipe(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-kpd-black-l250-d150',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Трубы одностенные',
            'slug' => 'truby-mono-kpd-black-test',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->create(['name' => 'КПД', 'slug' => 'kpd-black-pipe-test']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-011.849',
            'name' => 'КПД ЧЕРНЫЙ Труба 250мм 2мм ф150',
            'slug' => 'truba-kpd-250-mm-stal-2-mm-test',
            'price' => 42,
            'in_stock' => false,
            'stock_qty' => null,
            'images' => ['legacy/kpd-watermarked.jpg'],
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-022439',
            'name' => 'КПД КПД ЧЕРНЫЙ Шибер 250мм 2мм ф150',
            'slug' => 'kpd-black-d150-damper-wrong-candidate',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'kpd-black-pipe-l250-d150',
            'external_code' => 'УТ-00007818',
            'name' => 'КПД Труба ЧЁРНАЯ 250мм 2мм ф150',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.7477]],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261013-l',
            '--code' => ['УТ-00007818'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product->refresh();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame('КПД Труба дымоходная 250 мм, Ø150, сталь 2 мм, чёрная', $product->name);
        $this->assertSame('42.70', $product->price);
        $this->assertTrue($product->in_stock);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertSame(['img/products/generated/kpd-black-pipe-l250-d150-2mm.png'], $product->images);
        $this->assertSame('08ПС', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertStringContainsString('x-kamin.ru', $product->content_source_url);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нельзя объединять', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'УТ-00007818',
            'product_id' => $product->id,
            'confidence' => 'manual',
        ]);
    }

    public function test_it_links_and_reprices_the_exact_yarilo_stove_without_using_the_burzhuyka_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-yarilo-8mm',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Печи дровяные (отопительные)',
            'slug' => 'yarilo-stove-test',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->create(['name' => 'Царь-Печи', 'slug' => 'tsar-pechi-yarilo-test']);
        $yarilo = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-009.940',
            'name' => 'Печь Царь-Печи Ярило (8 мм)',
            'slug' => 'pech-tsar-pechi-yarilo-test',
            'price' => 648,
            'in_stock' => false,
            'stock_qty' => null,
            'images' => ['legacy/yarilo.jpg'],
            'content' => '<p>Покупательское описание печи Ярило.</p>',
        ]);
        $burzhuyka = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-009.932',
            'name' => 'Отопительная печь Царь-Печи Буржуйка (8 мм)',
            'slug' => 'pech-tsar-pechi-burzhuyka-test',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'yarilo-8mm',
            'external_code' => 'УТ-00007407',
            'name' => 'Отопительная печь "Ярило" (8мм)',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $burzhuyka->id, 'score' => 0.736]],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261013-m',
            '--code' => ['УТ-00007407'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $yarilo->refresh();
        $this->assertSame($yarilo->id, $item->product_id);
        $this->assertNotSame($burzhuyka->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame('699.00', $yarilo->price);
        $this->assertTrue($yarilo->in_stock);
        $this->assertSame('1', (string) $yarilo->stock_qty);
        $this->assertSame([
            'product/009/009940/5ef8a43be874e.jpg',
            'product/009/009940/5ef8a43be8810.jpg',
            'product/009/009940/5ef8a43be88c0.jpg',
        ], $yarilo->images);
        $this->assertStringNotContainsString('pechki66.ru', implode(' ', $yarilo->images));
        $this->assertStringContainsString('Покупательское описание', $yarilo->content);
        $this->assertStringContainsString('vishop.by', $yarilo->content_source_url);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'УТ-00007407',
            'product_id' => $yarilo->id,
            'confidence' => 'manual',
        ]);
    }

    public function test_it_links_and_refreshes_the_exact_zord_ugs_03_without_using_the_barrier_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-zord-ugs-03',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Сигнализаторы загазованности',
            'slug' => 'gas-detectors-zord-test',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->create(['name' => 'Электротеплоприбор', 'slug' => 'electroteplopribor-zord-test']);
        $zord = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-008.168',
            'name' => 'Сигнализатор загазованности ЗОРД УГС-03 (природный и сжиженный газ)',
            'h1' => 'Датчик природного/сжиженного газа CH4 ЗОРД УГС-02',
            'slug' => 'datchik-ch4-signalizator-zagazovannosti-zord-ugs-02-test',
            'price' => 42,
            'in_stock' => false,
            'stock_qty' => null,
            'images' => ['product/008/008168/61c1d022a721e.jpg'],
        ]);
        $barrier = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-005.704',
            'name' => 'Сигнализатор загазованности Барьер-CH4',
            'slug' => 'barrier-ch4-wrong-candidate',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'zord-ugs-03',
            'external_code' => 'УТ-00004265',
            'name' => 'Сигнализатор загазованности ЗОРД-УГС-03',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $barrier->id, 'score' => 0.8451]],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261013-n',
            '--code' => ['УТ-00004265'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $zord->refresh();
        $this->assertSame($zord->id, $item->product_id);
        $this->assertNotSame($barrier->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame('85.00', $zord->price);
        $this->assertTrue($zord->in_stock);
        $this->assertSame('2', (string) $zord->stock_qty);
        $this->assertSame($zord->name, $zord->h1);
        $this->assertSame(['product/008/008168/61c1d022a721e.jpg'], $zord->images);
        $this->assertStringNotContainsString('pechki66.ru', implode(' ', $zord->images));
        $this->assertStringNotContainsString('УГС-02', $zord->h1);
        $this->assertStringContainsString('gazby.by', $zord->content_source_url);
        $this->assertSame('Беларусь', collect($zord->specs)->firstWhere('key', 'Страна производства')['value']);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'УТ-00004265',
            'product_id' => $zord->id,
            'confidence' => 'manual',
        ]);
    }

    public function test_it_links_and_refreshes_the_exact_combined_zord_ugs_02_without_using_the_barrier_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-zord-ugs-02-combined',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Сигнализаторы загазованности',
            'slug' => 'gas-detectors-zord-combined-test',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->create(['name' => 'Электротеплоприбор', 'slug' => 'electroteplopribor-zord-combined-test']);
        $zord = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-011.094',
            'name' => 'Сигнализатор загазованности ЗОРД УГС-02 (СО+СH) комбинированный на 2 газа (угарный + горючий)',
            'slug' => 'zord-ugs-02-combined-test',
            'price' => 85,
            'in_stock' => false,
            'stock_qty' => null,
            'images' => ['product/0011/011094/61c43eaab7cbc.jpg'],
        ]);
        $barrier = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-005.704',
            'name' => 'Сигнализатор загазованности Барьер-CH4',
            'slug' => 'barrier-ch4-wrong-candidate-for-zord-ugs-02',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'zord-ugs-02-combined',
            'external_code' => 'УТ-00006897',
            'name' => 'Сигнализатор загазованости ЗОРД-УГС-02 (CO+CH)',
            'price_byn' => null,
            'stock_quantity' => 9,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $barrier->id, 'score' => 0.8356]],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261013-o',
            '--code' => ['УТ-00006897'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $zord->refresh();
        $this->assertSame($zord->id, $item->product_id);
        $this->assertNotSame($barrier->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame('105.00', $zord->price);
        $this->assertTrue($zord->in_stock);
        $this->assertSame('9', (string) $zord->stock_qty);
        $this->assertSame('Сигнализатор загазованности ЗОРД УГС-02 (CO+CH) комбинированный', $zord->name);
        $this->assertSame($zord->name, $zord->h1);
        $this->assertSame(['product/0011/011094/61c43eaab7cbc.jpg'], $zord->images);
        $this->assertStringNotContainsString('pechki66.ru', implode(' ', $zord->images));
        $this->assertStringContainsString('gazby.by', $zord->content_source_url);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($zord->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($zord->content));
        $this->assertSame('Беларусь', collect($zord->specs)->firstWhere('key', 'Страна производства')['value']);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'УТ-00006897',
            'product_id' => $zord->id,
            'confidence' => 'manual',
        ]);
    }

    public function test_it_links_and_refreshes_the_existing_electrolux_ech_wf_01_without_creating_a_hommyn_duplicate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-electrolux-ech-wf-01',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Электрические водонагреватели',
            'slug' => 'electric-electrolux-module-test',
            'parent_id' => 0,
        ]);
        $electrolux = Brand::query()->create(['name' => 'Electrolux', 'slug' => 'electrolux-ech-wf-test']);
        $module = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $electrolux->id,
            'sku' => 'PS-000.18707',
            'name' => 'Модуль Electrolux ECH/WF-01 Smart Wi-Fi',
            'h1' => 'Модуль Electrolux ECH/WF-01 Smart Wi-Fi',
            'slug' => 'electrolux-ech-wf-01-test',
            'price' => 185.13,
            'in_stock' => false,
            'stock_qty' => null,
            'images' => ['img/products/teplodvor-restored/electrolux/modul-electrolux-echwf-01-smart-wi-fi.png'],
        ]);
        $hommyn = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-003695',
            'name' => 'Модуль съемный управляющий HOMMYN HDN/WFN-02-02',
            'slug' => 'hommyn-hdn-wfn-wrong-candidate',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'electrolux-ech-wf-01',
            'external_code' => 'УТ-00003848',
            'name' => 'Модуль съёмный управляющий Electrolux ECH/WF-01 Smart WI-FI',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $hommyn->id, 'score' => 0.7792]],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261013-p',
            '--code' => ['УТ-00003848'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $module->refresh();
        $this->assertSame($module->id, $item->product_id);
        $this->assertNotSame($hommyn->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame('69.90', $module->price);
        $this->assertTrue($module->in_stock);
        $this->assertSame('1', (string) $module->stock_qty);
        $this->assertSame('Electrolux ECH/WF-01 Smart Wi-Fi съёмный управляющий модуль', $module->name);
        $this->assertSame($module->name, $module->h1);
        $this->assertSame('ECH/WF-01', collect($module->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('НС-1102748', collect($module->specs)->firstWhere('key', 'Код товара')['value']);
        $this->assertSame('7312970301012', collect($module->specs)->firstWhere('key', 'Штрихкод')['value']);
        $this->assertSame('USB Type-A', collect($module->specs)->firstWhere('key', 'Интерфейс подключения')['value']);
        $this->assertSame('70 × 24 × 14,5 мм', collect($module->specs)->firstWhere('key', 'Габариты')['value']);
        $this->assertSame(['img/products/teplodvor-restored/electrolux/modul-electrolux-echwf-01-smart-wi-fi.png'], $module->images);
        $this->assertStringContainsString('b2b.rusklimat.com', $module->content_source_url);
        $this->assertStringNotContainsString('HOMMYN', $module->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($module->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'УТ-00003848',
            'product_id' => $module->id,
            'confidence' => 'manual',
        ]);
    }

    public function test_it_links_and_corrects_the_existing_unipump_cp_25_40_180_instead_of_linking_apc(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unipump-cp-25-40-180',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Циркуляционные насосы',
            'slug' => 'circulation-pumps-unipump-test',
            'parent_id' => 0,
        ]);
        $unipump = Brand::query()->create(['name' => 'UNIPUMP', 'slug' => 'unipump-cp-test']);
        $pump = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $unipump->id,
            'sku' => 'PS-008.303',
            'name' => 'Насос циркуляционный Unipump CP 25-40 130',
            'h1' => 'Насос Unipump CP 25-40 180',
            'slug' => 'unipump-cp-25-40-180-test',
            'price' => 113.73,
            'in_stock' => false,
            'stock_qty' => null,
            'short_description' => 'Ошибочное описание модификации 130 мм.',
            'content' => '<p>Насос CP 25-40 с монтажной длиной 130 мм.</p>',
            'images' => ['product/008/008303/Unipump_cp1.jpg'],
        ]);
        $badHeadAttributeId = DB::table('attributes')->insertGetId([
            'category_id' => $category->id,
            'group_id' => 0,
            'sort_order' => 0,
            'type' => 'value',
            'name' => 'Максимальный напор , м',
            'suffix' => 'м',
            'in_filter' => false,
            'in_sort' => false,
            'in_product' => true,
            'in_brief' => false,
            'is_comparable' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_attribute_values')->insert([
            'product_id' => $pump->id,
            'attribute_id' => $badHeadAttributeId,
            'value' => '36',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $staleWidthAttributeId = DB::table('attributes')->insertGetId([
            'category_id' => $category->id,
            'group_id' => 0,
            'sort_order' => 0,
            'type' => 'value',
            'name' => 'Ширина, мм , см',
            'suffix' => 'см',
            'in_filter' => false,
            'in_sort' => false,
            'in_product' => true,
            'in_brief' => false,
            'is_comparable' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_attribute_values')->insert([
            'product_id' => $pump->id,
            'attribute_id' => $staleWidthAttributeId,
            'value' => '122',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $badConnectionAttributeId = DB::table('attributes')->insertGetId([
            'category_id' => $category->id,
            'group_id' => 0,
            'sort_order' => 0,
            'type' => 'value',
            'name' => 'Присоединительный размер , мм',
            'suffix' => 'мм',
            'in_filter' => false,
            'in_sort' => false,
            'in_product' => true,
            'in_brief' => false,
            'is_comparable' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('product_attribute_values')->insert([
            'product_id' => $pump->id,
            'attribute_id' => $badConnectionAttributeId,
            'value' => '25',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $apc = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-008.469',
            'name' => 'Насос циркуляционный APC GR 25-40 180',
            'slug' => 'apc-gr-25-40-180-wrong-candidate',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'unipump-cp-25-40-180',
            'external_code' => 'УТ-00003074',
            'name' => 'Насос циркуляц. (отопл.) СР 25-40 180,UNIPUMP',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $apc->id, 'score' => 0.74]],
        ]);

        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261013-q',
            '--code' => ['УТ-00003074'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $pump->refresh();
        $this->assertSame($pump->id, $item->product_id);
        $this->assertNotSame($apc->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame('114.30', $pump->price);
        $this->assertTrue($pump->in_stock);
        $this->assertSame('1', (string) $pump->stock_qty);
        $this->assertSame('UNIPUMP CP 25-40 180 циркуляционный насос', $pump->name);
        $this->assertSame($pump->name, $pump->h1);
        $this->assertSame('180 мм', collect($pump->specs)->firstWhere('key', 'Монтажная длина')['value']);
        $this->assertSame('1½″ × 1″', collect($pump->specs)->firstWhere('key', 'Присоединительный размер')['value']);
        $this->assertSame(['product/008/008303/Unipump_cp1.jpg'], $pump->images);
        $this->assertStringContainsString('unipump.ru', $pump->content_source_url);
        $this->assertStringNotContainsString('130 мм', $pump->short_description);
        $this->assertStringNotContainsString('130 мм', $pump->content);
        $this->assertStringNotContainsString('APC', $pump->content);
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $pump->id,
            'attribute_id' => $badHeadAttributeId,
            'value' => '4',
        ]);
        $this->assertDatabaseMissing('product_attribute_values', [
            'product_id' => $pump->id,
            'attribute_id' => $staleWidthAttributeId,
        ]);
        $this->assertDatabaseMissing('product_attribute_values', [
            'product_id' => $pump->id,
            'attribute_id' => $badConnectionAttributeId,
        ]);
        $this->assertGreaterThan(0, DB::table('product_attribute_values')->where('product_id', $pump->id)->count());

        DB::table('product_attribute_values')
            ->where('product_id', $pump->id)
            ->where('attribute_id', $badHeadAttributeId)
            ->update(['value' => '36']);
        DB::table('product_attribute_values')->insert([
            'product_id' => $pump->id,
            'attribute_id' => $staleWidthAttributeId,
            'value' => '122',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->artisan('integration:resolve-sbg-ambiguous', [
            '--batch' => 'exact-20261013-q',
            '--code' => ['УТ-00003074'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $pump->id,
            'attribute_id' => $badHeadAttributeId,
            'value' => '4',
        ]);
        $this->assertDatabaseMissing('product_attribute_values', [
            'product_id' => $pump->id,
            'attribute_id' => $staleWidthAttributeId,
        ]);
        $this->assertDatabaseMissing('product_attribute_values', [
            'product_id' => $pump->id,
            'attribute_id' => $badConnectionAttributeId,
        ]);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_article' => 'УТ-00003074',
            'product_id' => $pump->id,
            'confidence' => 'manual',
        ]);
    }
}

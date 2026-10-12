<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveSbgAmbiguousBatchCommandTest extends TestCase
{
    use RefreshDatabase;

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
}

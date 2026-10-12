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
}

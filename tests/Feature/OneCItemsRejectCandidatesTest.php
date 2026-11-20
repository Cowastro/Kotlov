<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Integrations\CommerceMlCatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OneCItemsRejectCandidatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejected_row_goes_to_not_found_and_the_importer_keeps_that(): void
    {
        $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'onec-door-170',
            'external_code' => 'УТ-00009721',
            'name' => 'Дверь DoorWood "Теплый день" 170х70 (короб.листва)',
            'match_status' => 'ambiguous',
            'match_method' => 'fuzzy_name',
            'match_confidence' => 0.73,
            'candidates' => [['product_id' => 4457, 'sku' => 'KOTLOV-004457', 'name' => 'Дверь 180х60', 'score' => 0.73]],
        ]);

        $this->artisan('integration:onec-items', ['--reject' => ['УТ-00009721']])
            ->expectsOutputToContain('metric.onec_items.rejected=1')->assertSuccessful();
        $this->assertSame('ambiguous', $item->fresh()->match_status);

        $this->artisan('integration:onec-items', ['--reject' => ['УТ-00009721'], '--apply' => true])->assertSuccessful();
        $item->refresh();
        $this->assertSame('unmatched', $item->match_status);
        $this->assertNull($item->product_id);
        $this->assertSame([4457], $item->candidates[0]['rejected_product_ids']);

        $guard = new \ReflectionMethod(CommerceMlCatalogImporter::class, 'hasUnchangedResearchDispute');
        $importer = app(CommerceMlCatalogImporter::class);
        $this->assertTrue($guard->invoke($importer, $item));
        $item->name = 'Дверь DoorWood "Теплый день" 170х70 (коробка липа)';
        $this->assertFalse($guard->invoke($importer, $item));
    }

    public function test_a_row_not_for_the_site_is_ignored_and_an_archived_card_comes_back_when_linked(): void
    {
        $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
        $part = IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => 'onec-stb',
            'external_code' => 'УТ-00001341', 'name' => 'Ограничитель STB', 'match_status' => 'unmatched']);
        $this->artisan('integration:onec-items', ['--ignore' => ['УТ-00001341'], '--apply' => true])
            ->expectsOutputToContain('metric.onec_items.ignored=1')->assertSuccessful();
        $this->assertSame('ignored', $part->fresh()->match_status);

        $category = Category::query()->firstOrCreate(['slug' => 'aksessuary'], ['name' => 'Аксессуары', 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'name' => 'Ballu BCT/EVU-3M', 'slug' => 'ballu-bctevu-3m',
            'price' => 96, 'is_active' => false, 'is_archived' => true]);
        $row = IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => 'onec-bct',
            'external_code' => 'УТ-00006137', 'name' => 'BCT/EVU-3M Блок управления', 'match_status' => 'unmatched']);
        $this->artisan('integration:onec-items', ['--link' => ['УТ-00006137:'.$card->id], '--unarchive' => true, '--apply' => true])
            ->expectsOutputToContain('audit.onec_items.unarchived='.$card->id)->assertSuccessful();
        $this->assertFalse((bool) $card->fresh()->is_archived);
        $this->assertSame($card->id, $row->fresh()->product_id);
    }

    public function test_a_row_linked_to_a_wrong_card_is_unlinked_and_the_card_keeps_the_price_of_its_own_row(): void
    {
        $source = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
        $supplier = Supplier::query()->create(['code' => 'sbg-test', 'name' => 'СБГ']);
        $source->forceFill(['supplier_id' => $supplier->id])->save();
        $category = Category::query()->firstOrCreate(['slug' => 'dymohody'], ['name' => 'Дымоходы', 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'name' => 'Тройник d150', 'slug' => 'tee-d150', 'price' => 180]);
        $right = IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => 'tee-150', 'external_code' => 'УТ-00007745',
            'external_sku' => 'TS.SHARED', 'name' => 'Тройник d150', 'match_status' => 'matched', 'product_id' => $card->id]);
        $wrong = IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => 'tee-115', 'external_code' => 'УТ-00008614',
            'external_sku' => 'TS.SHARED', 'name' => 'Тройник d115', 'match_status' => 'matched', 'product_id' => $card->id]);
        DB::table('supplier_products')->insert(['product_id' => $card->id, 'supplier_id' => $supplier->id,
            'supplier_article' => 'TS.SHARED', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('integration:onec-items', ['--unlink' => ['УТ-00008614']])
            ->expectsOutputToContain('audit.onec_items.unlinked='.$wrong->id.':'.$card->id)->assertSuccessful();
        $this->assertSame($card->id, (int) $wrong->fresh()->product_id);

        $this->artisan('integration:onec-items', ['--unlink' => ['УТ-00008614'], '--apply' => true])->assertSuccessful();
        $wrong->refresh();
        $this->assertNull($wrong->product_id);
        $this->assertSame('manual_research_unresolved', $wrong->match_method);
        $this->assertSame([$card->id], $wrong->candidates[0]['rejected_product_ids']);
        $this->assertSame($card->id, (int) $right->fresh()->product_id);
        $this->assertTrue(DB::table('supplier_products')->where('product_id', $card->id)->exists());

        $guard = new \ReflectionMethod(CommerceMlCatalogImporter::class, 'hasUnchangedResearchDispute');
        $this->assertTrue($guard->invoke(app(CommerceMlCatalogImporter::class), $wrong));
    }
}

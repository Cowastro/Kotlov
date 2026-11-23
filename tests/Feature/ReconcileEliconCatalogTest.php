<?php

namespace Tests\Feature;

use App\Console\Commands\ReconcileEliconCatalogCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCItem;
use App\Models\Product;
use App\Support\Catalog\EliconGasMeterCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReconcileEliconCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function catalog(): array
    {
        Storage::fake('local');
        $category = Category::firstOrCreate(['slug' => 'schetchiki-gaza'], ['name' => 'Счётчики']);
        $brand = Brand::firstOrCreate(['slug' => 'belomo'], ['name' => 'БелОМО']);
        $cards = [];
        foreach (EliconGasMeterCatalog::models() as $row) {
            if ($row['full_article'] === '8336.00.00.000-58') {
                continue;
            }
            $cards[$row['full_article']] = Product::create(['category_id' => $category->id, 'brand_id' => $brand->id,
                'name' => 'Старое название '.$row['article'], 'slug' => 'test-'.count($cards), 'sku' => 'TEST-'.count($cards),
                'specs' => ['Артикул производителя' => $row['full_article']], 'price' => 234.56,
                'stock_qty' => 17, 'in_stock' => false, 'is_active' => true, 'is_archived' => false]);
        }
        $source = IntegrationSource::updateOrCreate(['code' => 'onec'], ['name' => '1C']);
        $links = [];
        foreach (ReconcileEliconCatalogCommand::BINDINGS as $code => [$key, $article]) {
            OneCItem::create(['ref_key' => $key, 'code' => $code, 'description' => 'Старое неверное название L=250']);
            $links[] = IntegrationProduct::create(['integration_source_id' => $source->id, 'external_id' => $key,
                'name' => 'Неверное название', 'match_status' => 'ambiguous', 'price' => 147.69, 'stock_quantity' => 7]);
        }

        return [$cards, collect($links)->map(fn ($r) => $r->fresh())];
    }

    public function test_apply_binds_exact_articles_preserves_money_and_stock_and_is_repeatable(): void
    {
        [$cards, $links] = $this->catalog();
        $count = Product::count();
        $before = $links->map(fn ($r) => Arr::except($r->getAttributes(), ['product_id', 'match_status', 'match_method', 'match_confidence', 'matched_at', 'updated_at']))->all();
        $this->artisan('supplier:reconcile-elicon-catalog')->assertSuccessful();
        $this->assertSame($count, Product::count());
        $this->artisan('supplier:reconcile-elicon-catalog --apply')->assertSuccessful();
        $this->assertSame($count + 2, Product::count());
        $pending = Product::where('sku', 'BELOMO-8181-24')->sole();
        $artifacts = (new \ReflectionMethod(\App\Console\Commands\SyncBelomoPriceListCommand::class, 'catalogArtifacts'))
            ->invoke(new \App\Console\Commands\SyncBelomoPriceListCommand, []);
        $this->assertNotContains($pending->id, array_column($artifacts, 'id'));
        $this->assertNotContains(Product::where('sku', 'BELOMO-8336-58')->value('id'), array_column($artifacts, 'id'));
        $this->assertSame($before, $links->map(fn ($r) => Arr::except($r->fresh()->getAttributes(), ['product_id', 'match_status', 'match_method', 'match_confidence', 'matched_at', 'updated_at']))->all());
        foreach ($cards as $card) {
            $this->assertEquals(234.56, $card->fresh()->price);
            $this->assertSame(17, $card->fresh()->stock_qty);
        }
        $this->assertSame($cards['8072.00.00.000-21']->id, $links[2]->fresh()->product_id);
        $this->assertSame(['Артикул производителя' => '8181.00.00.000-24'], $pending->specs);
        $this->assertFalse($pending->in_stock);
        $this->assertTrue($pending->requires_price_confirmation);
        $this->assertSame($pending->id, $links[1]->fresh()->product_id);
        $this->artisan('supplier:reconcile-elicon-catalog --apply')->assertSuccessful();
        $this->assertSame($count + 2, Product::count());
    }

    public function test_existing_conflicting_link_aborts_before_creating_or_renaming_cards(): void
    {
        [$cards, $links] = $this->catalog();
        $links[2]->update(['product_id' => $cards['8072.00.00.000-23']->id]);
        $count = Product::count();
        $name = $cards['8072.00.00.000-21']->name;
        $this->artisan('supplier:reconcile-elicon-catalog --apply')->assertFailed();
        $this->assertSame($count, Product::count());
        $this->assertSame($name, $cards['8072.00.00.000-21']->fresh()->name);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }
}

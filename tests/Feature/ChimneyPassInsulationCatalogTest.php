<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChimneyPassInsulationCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reuses_a_1c_item_and_only_creates_missing_insulation_cards(): void
    {
        Category::query()->updateOrCreate(['slug' => 'krepleniya-dymohod'], [
            'name' => 'Крепления и монтаж дымохода',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $supplier = Supplier::query()->updateOrCreate(['code' => 'sanbusinessgroup'], [
            'name' => 'ООО «СанБизнесГруп»',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->firstOrCreate(['code' => 'onec'], [
            'supplier_id' => $supplier->id,
            'name' => '1С СанБизнесГруп',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        $source->update(['supplier_id' => $supplier->id, 'is_active' => true]);
        $wool = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'basalt-wool-3kg',
            'name' => 'Базальтовая вата для набивки ППУ ( 3 кг)',
            'match_status' => 'unmatched',
        ]);

        $migration = require database_path('migrations/2026_10_27_120000_ensure_chimney_pass_insulation_products.php');
        $migration->up();
        $migration->up();

        $wool->refresh();
        $this->assertNotNull($wool->product_id);
        $this->assertSame('matched', $wool->match_status);
        $this->assertSame('exact_accessory_name', $wool->match_method);
        $this->assertSame(1, Product::query()->where('name', 'like', '%Базальтовая вата для набивки ППУ%')->count());
        $this->assertSame(1, Product::query()->where('name', 'like', '%Базальтовый картон%')->count());
        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplier->id,
            'product_id' => $wool->product_id,
            'supplier_article' => 'Базальтовая вата',
            'match_confidence' => 'manual_price_list',
        ]);
    }
}

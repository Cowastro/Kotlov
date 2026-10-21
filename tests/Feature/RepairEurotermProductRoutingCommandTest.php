<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Integrations\CommerceMlCatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairEurotermProductRoutingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_restores_euroterm_ownership_and_blocks_foreign_one_c_rematching(): void
    {
        Product::query()->update(['is_active' => false]);
        $category = Category::query()->create([
            'name' => 'Евротерм проверка',
            'slug' => 'euroterm-routing-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $euroterm = Supplier::query()->updateOrCreate(['code' => 'euroterm'], [
            'name' => 'ООО «Евротерм»',
            'is_active' => true,
        ]);
        $sbg = Supplier::query()->updateOrCreate(['code' => 'sanbusinessgroup'], [
            'name' => 'ООО «СанБизнесГруп»',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $sbg->id,
            'code' => 'onec-euroterm-conflict',
            'name' => '1С СанБизнесГруп',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'supplier_id' => $sbg->id,
            'name' => 'Удлинитель дымохода коаксиальный',
            'slug' => 'euroterm-routing-product',
            'sku' => 'EUROTERM-Y025M',
            'price' => 56.39,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
            'requires_price_confirmation' => false,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $euroterm->id,
            'product_id' => $product->id,
            'supplier_article' => 'Y-025M',
            'price_byn' => 45,
            'stock_quantity' => 6,
            'in_stock' => true,
            'match_status' => 'matched',
            'last_stock_synced_at' => now(),
            'last_synced_at' => now(),
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $sbg->id,
            'product_id' => $product->id,
            'supplier_article' => 'WRONG-SBG',
            'price_byn' => 40,
            'match_status' => 'matched',
        ]);
        $integration = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $product->id,
            'external_id' => 'sbg-euroterm-conflict',
            'external_sku' => 'Y-025M',
            'name' => $product->name,
            'match_status' => 'matched',
            'match_method' => 'exact_name',
            'match_confidence' => 0.9,
            'matched_at' => now(),
        ]);

        $this->artisan('supplier:repair-euroterm-routing')
            ->expectsOutputToContain('metric.euroterm_routing.sbg_supplier_links=1')
            ->expectsOutputToContain('metric.euroterm_routing.sbg_integration_links=1')
            ->assertSuccessful();
        $this->assertSame($sbg->id, $product->fresh()->supplier_id);

        $this->artisan('supplier:repair-euroterm-routing', ['--apply' => true])
            ->expectsOutputToContain('metric.euroterm_routing.status=applied')
            ->expectsOutputToContain('metric.euroterm_routing.supplier_links_removed=1')
            ->expectsOutputToContain('metric.euroterm_routing.integration_links_detached=1')
            ->assertSuccessful();

        $this->assertSame($euroterm->id, $product->fresh()->supplier_id);
        $this->assertDatabaseMissing('supplier_products', [
            'supplier_id' => $sbg->id,
            'product_id' => $product->id,
        ]);
        $integration->refresh();
        $this->assertNull($integration->product_id);
        $this->assertSame('unmatched', $integration->match_status);

        $stats = app(CommerceMlCatalogImporter::class)->rematchSource($source->code);
        $this->assertSame(1, $stats['unmatched']);
        $integration->refresh();
        $this->assertNull($integration->product_id);
        $this->assertSame('unmatched', $integration->match_status);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\IntegrationSource;
use App\Models\SupplierProduct;
use App\Models\SupplierChannelTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AlignMetabelTsarPechiSanBusinessGroupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_moves_metabel_and_verified_tsar_pechi_routes_to_sanbusinessgroup(): void
    {
        Product::query()->update(['is_active' => false]);
        $category = Category::query()->create([
            'name' => 'Печи для проверки',
            'slug' => 'sbg-brand-alignment-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $meta = Brand::query()->updateOrCreate(['slug' => 'metabel'], ['name' => 'Мета-Бел', 'is_active' => true]);
        $tsar = Brand::query()->updateOrCreate(['slug' => 'tsar-pechi'], ['name' => 'Царь-Печи', 'is_active' => true]);
        $sbg = Supplier::query()->updateOrCreate(
            ['code' => 'sanbusinessgroup'],
            ['name' => 'ООО «СанБизнесГруп»', 'is_active' => false],
        );
        $legacy = Supplier::query()->updateOrCreate(
            ['code' => 'metabel'],
            ['name' => 'Мета-Бел', 'is_active' => true],
        );
        $source = IntegrationSource::query()->create([
            'supplier_id' => $sbg->id,
            'code' => 'sbg-brand-alignment-source',
            'name' => '1С СанБизнесГруп — тест',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        SupplierChannelTransition::query()->create([
            'supplier_id' => $sbg->id,
            'integration_source_id' => $source->id,
            'status' => SupplierChannelTransition::STATUS_LEGACY_DISABLED,
            'snapshot' => [],
            'legacy_disabled_at' => now(),
        ]);

        $metaProduct = $this->product($category->id, $meta->id, 15001, 'META-TEST');
        $tsarProduct = $this->product($category->id, $tsar->id, 9906, 'TSAR-TEST');
        SupplierProduct::query()->create([
            'supplier_id' => $legacy->id,
            'product_id' => $metaProduct->id,
            'product_sku' => $metaProduct->sku,
            'supplier_article' => 'META-ARTICLE',
            'price_byn' => 500,
            'match_status' => 'matched',
        ]);

        $this->artisan('supplier:align-metabel-tsarpechi-sbg')
            ->expectsOutputToContain('metric.metabel_links_to_move=1')
            ->expectsOutputToContain('metric.tsar_links_to_create=1')
            ->assertSuccessful();
        $this->assertDatabaseMissing('supplier_products', ['supplier_id' => $sbg->id]);

        $this->artisan('supplier:align-metabel-tsarpechi-sbg', ['--apply' => true])
            ->expectsOutputToContain('metric.status=applied')
            ->expectsOutputToContain('metric.links_written=2')
            ->assertSuccessful();

        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $sbg->id,
            'product_id' => $metaProduct->id,
            'supplier_article' => 'META-ARTICLE',
        ]);
        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $sbg->id,
            'product_id' => $tsarProduct->id,
            'match_confidence' => 'manual_price_list',
        ]);
        $this->assertDatabaseMissing('supplier_products', ['supplier_id' => $legacy->id]);
        $this->assertDatabaseHas('brand_supplier', ['brand_id' => $meta->id, 'supplier_id' => $sbg->id]);
        $this->assertDatabaseHas('brand_supplier', ['brand_id' => $tsar->id, 'supplier_id' => $sbg->id]);
        $this->assertTrue($sbg->fresh()->is_active);
        // A specifically verified online price-list link remains operational
        // alongside 1C. Generic stale legacy links are still rejected.
        $this->assertTrue($metaProduct->fresh()->hasAssignedSupplier());
        $this->assertTrue($tsarProduct->fresh()->hasAssignedSupplier());
    }

    private function product(int $categoryId, int $brandId, int $id, string $sku): Product
    {
        $product = new Product([
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'name' => 'Товар '.$sku,
            'slug' => mb_strtolower($sku),
            'sku' => $sku,
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'requires_price_confirmation' => false,
        ]);
        $product->id = $id;
        $product->save();

        return $product;
    }
}

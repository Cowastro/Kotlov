<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\Supplier;
use App\Models\SupplierChannelTransition;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MergeSanBusinessGroupSuppliersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_and_apply_moves_every_reference_into_sanbusinessgroup(): void
    {
        $sbg = Supplier::query()->updateOrCreate(['code' => 'sanbusinessgroup'], ['name' => 'ООО «СанБизнесГруп»', 'currency' => 'BYN', 'is_active' => true]);
        $eco = Supplier::query()->updateOrCreate(['code' => 'ecokamin'], ['name' => 'ООО «СанБизнесГруп»', 'currency' => 'RUB', 'is_active' => true, 'contact' => 'https://ecokamin.ru']);
        $onec = IntegrationSource::query()->create([
            'code' => 'onec-sbg-merge-test', 'name' => '1С СанБизнесГруп', 'supplier_id' => $sbg->id,
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'], 'is_active' => true, 'update_stock' => true,
        ]);
        DB::table('supplier_channel_transitions')->insert([
            'supplier_id' => $sbg->id, 'integration_source_id' => $onec->id, 'status' => SupplierChannelTransition::STATUS_LEGACY_DISABLED,
            'snapshot' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $category = Category::query()->create(['name' => 'Печи-камины тест', 'slug' => 'sbg-merge-test', 'parent_id' => 0]);
        $brand = Brand::query()->create(['name' => 'ЭкоКамин тест', 'slug' => 'ecokamin-merge-test']);
        $stove = Product::query()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'Печь-камин тест', 'slug' => 'sbg-merge-stove', 'price' => 2500, 'is_active' => true]);
        $shared = Product::query()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'Камин общий', 'slug' => 'sbg-merge-shared', 'price' => 900, 'is_active' => true]);

        $moved = SupplierProduct::query()->create([
            'supplier_id' => $eco->id, 'product_id' => $stove->id, 'supplier_article' => 'ECO-1',
            'source_url' => 'https://www.ecokamin.ru/catalog/1/', 'price' => 50000, 'currency' => 'RUB',
            'currency_rate' => 0.0365, 'price_byn' => 1825, 'match_confidence' => 'auto_name', 'in_stock' => true,
        ]);
        SupplierProduct::query()->create(['supplier_id' => $sbg->id, 'product_id' => $shared->id, 'supplier_article' => 'SAME', 'price_byn' => 700, 'match_confidence' => 'exact_supplier_article']);
        SupplierProduct::query()->create(['supplier_id' => $eco->id, 'product_id' => $shared->id, 'supplier_article' => 'SAME', 'source_url' => 'https://ecokamin.ru/x', 'price_byn' => 650]);
        DB::table('brand_supplier')->insert(['brand_id' => $brand->id, 'supplier_id' => $eco->id, 'created_at' => now(), 'updated_at' => now()]);
        ProductWarehouseStock::query()->create(['product_id' => $stove->id, 'supplier_id' => $eco->id, 'warehouse_code' => 'ecokamin-moscow', 'warehouse_name' => 'Склад Москва', 'available_quantity' => 4]);
        DB::table('supplier_syncs')->updateOrInsert(['key' => 'ecokamin_stoves'], ['supplier_id' => $eco->id, 'name' => 'ООО «СанБизнесГруп»', 'code' => 'ecokamin', 'title' => 'ЭкоКамин', 'command' => 'supplier:sync-ecokamin-stoves', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('supplier:merge-sanbusinessgroup')
            ->expectsOutputToContain('metric.sbg_merge.target_legacy_disabled=1')
            ->assertSuccessful();
        $this->assertSame($eco->id, $moved->fresh()->supplier_id);

        $this->artisan('supplier:merge-sanbusinessgroup --apply')
            ->expectsOutputToContain('metric.sbg_merge.remaining_source_links=0')
            ->assertSuccessful();

        $moved->refresh();
        $this->assertSame($sbg->id, $moved->supplier_id);
        $this->assertSame('official_source', $moved->match_confidence);
        $this->assertSame('RUB', $moved->currency);
        $this->assertSame(1, SupplierProduct::query()->where('supplier_article', 'SAME')->count());
        $this->assertSame('exact_supplier_article', SupplierProduct::query()->where('supplier_article', 'SAME')->value('match_confidence'));
        $this->assertSame($sbg->id, ProductWarehouseStock::query()->sole()->supplier_id);
        $this->assertDatabaseHas('brand_supplier', ['brand_id' => $brand->id, 'supplier_id' => $sbg->id]);
        $this->assertDatabaseHas('supplier_syncs', ['key' => 'ecokamin_stoves', 'supplier_id' => $sbg->id, 'code' => 'sanbusinessgroup']);

        $eco->refresh();
        $this->assertFalse($eco->is_active);
        $this->assertSame('ecokamin-merged-into-sanbusinessgroup', $eco->code);
        $this->assertSame(1, Supplier::query()->where('name', 'like', '%СанБизнесГруп%')->where('is_active', true)->count());
        $this->assertTrue($stove->fresh()->hasAssignedSupplier());

        $this->artisan('supplier:merge-sanbusinessgroup')
            ->expectsOutputToContain('metric.sbg_merge.source_found=0')
            ->assertSuccessful();
    }
}

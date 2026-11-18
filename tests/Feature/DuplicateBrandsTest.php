<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\MergeDuplicateBrands;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DuplicateBrandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_preserves_products_suppliers_metadata_old_filters_and_urls(): void
    {
        Storage::fake('local');
        // Reproduce a legacy database before the new unique constraint existed.
        Schema::table('brands', fn (Blueprint $t) => $t->dropUnique('brands_name_unique'));
        $first = Brand::create(['name' => 'Test LD', 'slug' => 'test-ld', 'is_active' => false, 'producer' => 'Factory']);
        $secondId = DB::table('brands')->insertGetId(['name' => ' test ld ', 'slug' => 'test-ld-2', 'is_active' => true, 'content' => 'Editorial description', 'created_at' => now(), 'updated_at' => now()]);
        $category = Category::create(['name' => 'Brand test', 'slug' => 'brand-dedup-test', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Brand supplier', 'code' => 'brand-dedup', 'is_active' => true]);
        DB::table('brand_supplier')->insert(['brand_id' => $secondId, 'supplier_id' => $supplier->id, 'created_at' => now(), 'updated_at' => now()]);
        $rule = \App\Models\B2bPricingRule::create(['supplier_id' => $supplier->id, 'name' => 'Brand rule', 'scope_type' => 'brand', 'scope_id' => $secondId, 'calculation_type' => 'markup', 'value' => 20]);
        $ruleBefore = $rule->fresh()->getAttributes();
        $products = collect([$first->id, $secondId])->map(fn ($id) => Product::create(['name' => 'Test '.$id, 'sku' => 'BRAND-'.$id, 'slug' => 'brand-product-'.$id, 'brand_id' => $id, 'category_id' => $category->id, 'is_active' => true, 'is_archived' => false, 'price' => 123, 'stock_qty' => 7]));
        $before = $products->mapWithKeys(fn ($p) => [$p->id => $p->fresh()->getAttributes()]);
        $migration = require database_path('migrations/2026_11_18_190000_merge_exact_duplicate_brands.php');
        $migration->up();
        $migration->up();
        foreach ($products as $p) {
            $this->assertSame($first->id, $p->fresh()->brand_id);
            $this->assertEquals(array_diff_key($before[$p->id], ['brand_id' => true]), array_diff_key($p->fresh()->getAttributes(), ['brand_id' => true]));
        }
        $this->assertDatabaseMissing('brands', ['id' => $secondId]);
        $this->assertDatabaseHas('brand_supplier', ['brand_id' => $first->id, 'supplier_id' => $supplier->id]);
        $this->assertSame('Editorial description', $first->fresh()->content);
        $this->assertTrue($first->fresh()->is_active);
        $this->assertSame('Factory', $first->fresh()->producer);
        $this->assertSame($first->id, (int) $rule->fresh()->scope_id);
        $this->assertSame(array_diff_key($ruleBefore, ['scope_id' => true]), array_diff_key($rule->fresh()->getAttributes(), ['scope_id' => true]));
        $this->assertSame($first->id, Brand::canonicalId($secondId));
        $this->assertNotEmpty(Storage::disk('local')->files('private/brand-merges'));
        $this->assertSame([], app(MergeDuplicateBrands::class)->run());
        $this->get('/brands/test-ld-2')->assertRedirect('/brands/test-ld')->assertStatus(301);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get(route('b2b.catalog', ['workspace' => 1, 'brand' => $secondId]))->assertOk()
            ->assertViewHas('brandId', $first->id)->assertViewHas('products', fn ($p) => $p->total() === 2)
            ->assertViewHas('brands', fn ($b) => $b->where('name', 'Test LD')->count() === 1);
    }

    public function test_import_reuses_normalized_brand_but_keeps_different_full_names(): void
    {
        $brand = Brand::forImport('Test MAGNUM');
        $this->assertSame($brand->id, Brand::forImport("  test\u{00a0}magnum  ")->id);
        $this->assertNotSame($brand->id, Brand::forImport('Test MAGNUM Steel')->id);
        $this->expectException(ValidationException::class);
        Brand::create(['name' => 'TEST MAGNUM ', 'slug' => 'another-test-magnum']);
    }
}

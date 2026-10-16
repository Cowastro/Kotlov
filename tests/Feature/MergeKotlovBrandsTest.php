<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MergeKotlovBrandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_preserves_products_supplier_links_and_commercial_fields_and_redirects_old_brand(): void
    {
        Storage::fake('local');
        $canonical = Brand::query()->firstOrCreate(['slug' => 'kotlov'], ['name' => 'KOTLOV', 'is_active' => true]);
        $category = Category::query()->create(['name' => 'Test merge', 'slug' => 'merge-kotlov-test']);
        $supplier = Supplier::query()->firstOrCreate(['code' => 'merge-brand-test'], ['name' => 'Test supplier']);
        $products = [];
        foreach (['kotlov-pro' => 'KOTLOV PRO', 'koktlov' => 'Koktlov'] as $slug => $name) {
            $id = DB::table('brands')->insertGetId(['slug' => $slug, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('brand_supplier')->insert(['brand_id' => $id, 'supplier_id' => $supplier->id, 'created_at' => now(), 'updated_at' => now()]);
            $products[] = Product::query()->create([
                'category_id' => $category->id, 'brand_id' => $id, 'name' => 'Котёл KOTLOV PRO 95',
                'slug' => 'merge-'.$slug, 'sku' => 'MERGE-'.$slug,
                'price' => 12345, 'stock_qty' => 6, 'in_stock' => true,
                'specs' => [['key' => 'Бренд', 'value' => $name], ['key' => 'Мощность', 'value' => '95 кВт']],
            ]);
        }
        $before = array_map(fn ($product) => $product->getAttributes(), $products);
        $ge = Brand::query()->where('slug', 'kotlov-ge')->first();
        $geBefore = $ge?->getAttributes();
        $migration = require database_path('migrations/2026_10_16_180000_merge_kotlov_pro_into_kotlov.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseMissing('brands', ['slug' => 'kotlov-pro']);
        $this->assertDatabaseMissing('brands', ['slug' => 'koktlov']);
        $this->assertSame(1, DB::table('brand_supplier')->where('brand_id', $canonical->id)->where('supplier_id', $supplier->id)->count());
        foreach ($products as $index => $product) {
            $after = $product->fresh();
            $this->assertSame($canonical->id, $after->brand_id);
            $this->assertSame('KOTLOV', $after->specs[0]['value']);
            foreach (array_diff(array_keys($before[$index]), ['brand_id', 'specs']) as $field) {
                $this->assertEquals($before[$index][$field], $after->getAttributes()[$field], $field);
            }
        }
        if ($ge) {
            $this->assertSame($geBefore, $ge->fresh()->getAttributes());
        }
        Storage::disk('local')->assertExists('private/brand-merges/kotlov-2026-10-16.json');
        $this->get('/brands/kotlov-pro')->assertStatus(301)->assertRedirect('/brands/kotlov');
        $this->get('/brands/koktlov')->assertStatus(301)->assertRedirect('/brands/kotlov');
    }

    public function test_a_new_alias_cannot_recreate_a_second_kotlov_brand(): void
    {
        Brand::query()->firstOrCreate(['slug' => 'kotlov'], ['name' => 'KOTLOV']);
        foreach (['Kotlov Pro', 'Koktlov', 'KOTLOV'] as $name) {
            try {
                Brand::query()->create(['name' => $name, 'slug' => 'duplicate-'.strtolower(str_replace(' ', '-', $name))]);
                $this->fail('A duplicate KOTLOV brand was accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('name', $exception->errors());
            }
        }
    }
}

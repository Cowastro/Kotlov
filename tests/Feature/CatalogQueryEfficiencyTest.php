<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\WaterHeaterCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogQueryEfficiencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_nested_subcategory_counts_preserve_brand_selection_without_repeated_tree_and_schema_queries(): void
    {
        $root = $this->category('efficiency-root');
        $first = $this->category('efficiency-first', $root);
        $leaf = $this->category('efficiency-leaf', $first);
        $second = $this->category('efficiency-second', $root);
        $this->category('efficiency-empty', $root);
        $brand = Brand::create(['name' => 'Efficiency', 'slug' => 'efficiency-brand']);
        $other = Brand::create(['name' => 'Other', 'slug' => 'efficiency-other']);
        $this->product($first, 'first-match', $brand);
        $this->product($leaf, 'leaf-other', $other);
        $this->product($second, 'second-match', $brand);
        $hidden = $this->product($second, 'archived', $brand);
        $hidden->update(['is_archived' => true]);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query;
        });
        foreach ([null => [$first->id => 2, $second->id => 1], $brand->id => [$first->id => 1, $second->id => 1]] as $brandId => $expected) {
            $queries = [];
            $response = $this->get('/'.$root->slug.($brandId ? '?brand='.$brandId : ''))->assertOk();
            $this->assertSame($expected, $response->viewData('subcategories')->pluck('products_count', 'id')->all());
            $treeQueries = collect($queries)->filter(fn ($query) => preg_match('/select\s+id,\s*parent_id\s+from\s+categories\b/i', str_replace(['`', '"'], '', $query->sql)));
            $schemaQueries = collect($queries)->filter(fn ($query) => (str_contains($query->sql, 'sqlite_master') || str_contains($query->sql, 'information_schema.tables'))
                && (str_contains($query->sql, 'wishlist_items') || in_array('wishlist_items', $query->bindings, true))
            );
            $this->assertCount(1, $treeQueries);
            $this->assertCount(1, $schemaQueries);
        }
    }

    public function test_specification_refresh_does_not_block_the_response_or_extend_the_freshness_limit(): void
    {
        $category = $this->category('electric');
        $brand = Brand::create(['name' => 'Tank', 'slug' => 'tank-test-brand']);
        $product = $this->product($category, 'cached-tank', $brand);
        $product->update(['specs' => ['Объем бака, л' => '80 л']]);
        $catalog = app(WaterHeaterCatalog::class);
        $ids = collect([$category->id]);
        $initial = $catalog->facts('electric', $ids);
        $this->assertStringContainsString('80', json_encode($initial));
        $product->update(['specs' => ['Объем бака, л' => '120 л']]);
        $this->travel(245)->seconds();
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $this->assertSame($initial, $catalog->facts('electric', $ids));
        $this->assertSame(0, $queries);
        app(DeferredCallbackCollection::class)->invoke();
        $this->assertGreaterThan(0, $queries);
        $refreshed = $catalog->facts('electric', $ids);
        $this->assertStringContainsString('120', json_encode($refreshed));
        $this->assertNotSame($initial, $refreshed);
        $product->update(['specs' => ['Объем бака, л' => '200 л']]);
        $this->travel(301)->seconds();
        $this->assertStringContainsString('200', json_encode($catalog->facts('electric', $ids)));
        $this->travelBack();
    }

    private function category(string $slug, ?Category $parent = null): Category
    {
        return Category::create(['name' => $slug, 'slug' => $slug, 'parent_id' => $parent?->id ?? 0, 'is_active' => true]);
    }

    private function product(Category $category, string $slug, Brand $brand): Product
    {
        return Product::create([
            'category_id' => $category->id, 'brand_id' => $brand->id, 'name' => $slug,
            'slug' => $slug, 'sku' => $slug, 'price' => 100, 'is_active' => true,
            'is_archived' => false, 'in_stock' => true, 'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
    }
}

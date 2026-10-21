<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoveVarmegaBoilerProductsToManifoldsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_varmega_products_misplaced_in_boilers_are_moved_to_manifolds(): void
    {
        $boilers = Category::query()->create([
            'name' => 'Котлы отопления',
            'slug' => 'kotly',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $manifolds = Category::query()->create([
            'name' => 'Гребёнки',
            'slug' => 'grebenki',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $otherCategory = Category::query()->create([
            'name' => 'Арматура',
            'slug' => 'test-armatura',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $varmega = Brand::query()->where('slug', 'varmega')->firstOrFail();
        $otherBrand = Brand::query()->create(['name' => 'Другой бренд', 'slug' => 'other-brand']);

        $misplaced = $this->product($boilers, $varmega, 'Varmega VM15403');
        $realBoiler = $this->product($boilers, $otherBrand, 'Настоящий котёл');
        $correctVarmega = $this->product($otherCategory, $varmega, 'Арматура Varmega');

        $migration = require database_path('migrations/2026_10_21_252000_move_varmega_boiler_products_to_manifolds.php');
        $migration->up();

        $this->assertSame($manifolds->id, $misplaced->fresh()->category_id);
        $this->assertSame($boilers->id, $realBoiler->fresh()->category_id);
        $this->assertSame($otherCategory->id, $correctVarmega->fresh()->category_id);
    }

    private function product(Category $category, Brand $brand, string $name): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'sku' => 'TEST-'.uniqid(),
            'price' => 100,
            'is_active' => true,
        ]);
    }
}

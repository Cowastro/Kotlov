<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoveStenHeatingElementsOutOfBoilersMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_sten_heating_elements_are_moved_out_of_boiler_categories(): void
    {
        $boilers = Category::query()->create([
            'name' => 'Котлы отопления',
            'slug' => 'kotly',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $electricBoilers = Category::query()->create([
            'name' => 'Электрические котлы',
            'slug' => 'elektricheskie',
            'parent_id' => $boilers->id,
            'is_active' => true,
        ]);
        $heatingElements = Category::query()->create([
            'name' => 'Электрические ТЭНы для отопления',
            'slug' => 'elektricheskie-teny-dlya-otopleniya',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $sten = Brand::query()->whereRaw('LOWER(name) = ?', ['стэн'])->first()
            ?? Brand::query()->create([
                'name' => 'СТЭН',
                'slug' => 'sten-migration-test',
                'is_active' => true,
            ]);
        $otherBrand = Brand::query()->create(['name' => 'Другой бренд', 'slug' => 'other-brand-sten-test']);

        $tenb = $this->product($electricBoilers, $sten, 'СТЭН ТЭНБ - 6 - G1½» с колпаком');
        $tnbr = $this->product($boilers, $sten, 'СТЭН ТНБР-3-1 1/4 - Т с терморегулятором');
        $realBoiler = $this->product($electricBoilers, $sten, 'СТЭН Котел «СТЭН ЭКО 5» 220');
        $otherBrandTen = $this->product($electricBoilers, $otherBrand, 'ТЭНБ - 6');

        $migration = require database_path('migrations/2026_10_21_253000_move_sten_heating_elements_out_of_boilers.php');
        $migration->up();

        $this->assertSame($heatingElements->id, $tenb->fresh()->category_id);
        $this->assertSame($heatingElements->id, $tnbr->fresh()->category_id);
        $this->assertSame($electricBoilers->id, $realBoiler->fresh()->category_id);
        $this->assertSame($electricBoilers->id, $otherBrandTen->fresh()->category_id);
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

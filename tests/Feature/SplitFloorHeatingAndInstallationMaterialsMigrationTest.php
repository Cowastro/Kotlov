<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SplitFloorHeatingAndInstallationMaterialsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_clear_floor_heating_and_installation_material_branches(): void
    {
        $chimneys = $this->category('Дымоходы', 'dymohody');
        $chimneyInstallation = $this->category('Крепления и монтаж', 'krepleniya-dymohod', $chimneys);
        $floorHeating = $this->category('Теплый пол', 'teplyj-pol');
        $materials = $this->category('Герметики и монтажные материалы', 'germetiki-i-montazhnye-materialy');

        $groups = [
            'nagrevatelnye-maty' => [
                'KOTLOV-001803', 'KOTLOV-001808', 'KOTLOV-001764',
                'KOTLOV-001833', 'KOTLOV-001838', 'KOTLOV-001839',
                'KOTLOV-001744', 'KOTLOV-001746', 'KOTLOV-001756',
                'PS-000.18655', 'PS-000.18981', 'PS-000.18982', 'PS-000.18749',
            ],
            'nagrevatelnye-kabeli' => [
                'KOTLOV-001863', 'KOTLOV-001866', 'KOTLOV-001779', 'KOTLOV-001780',
                'PS-000.18746', 'PS-000.18745', 'PS-000.18747', 'PS-000.18748',
            ],
            'ik-plenochnyj-pol' => ['PS-000.18730', 'PS-000.18731', 'PS-000.18728', 'PS-000.18729'],
            'pechnye-i-kaminnye-germetiki' => ['KOTLOV-030272', 'PS-009.746', 'PS-009.747'],
            'krovelnye-germetiki' => ['PS-009.744', 'PS-009.745'],
            'termostoykie-kraski' => ['PS-007.697'],
            'uplotnitelnye-shnury' => ['KOTLOV-030273'],
        ];

        foreach (array_slice($groups, 0, 3, true) as $skus) {
            foreach ($skus as $sku) {
                $this->product($floorHeating, $sku);
            }
        }
        foreach (array_slice($groups, 3, null, true) as $skus) {
            foreach ($skus as $sku) {
                $this->product($materials, $sku);
            }
        }

        $migration = require database_path('migrations/2026_10_27_253000_split_floor_heating_and_installation_materials.php');
        $migration->up();

        foreach ($groups as $slug => $skus) {
            $category = Category::query()->where('slug', $slug)->firstOrFail();
            $this->assertTrue($category->is_active);
            $this->assertSame(count($skus), Product::query()->where('category_id', $category->id)->count());
        }

        $this->assertSame('Электрический тёплый пол', $floorHeating->refresh()->name);
        $this->assertSame($chimneyInstallation->id, $materials->refresh()->parent_id);
        $this->assertSame(25, Product::query()->whereIn('sku', collect(array_slice($groups, 0, 3, true))->flatten())->count());
        $this->assertSame(7, Product::query()->whereIn('sku', collect(array_slice($groups, 3, null, true))->flatten())->count());

        $migration->down();

        $this->assertSame('Теплый пол', $floorHeating->refresh()->name);
        $this->assertSame(0, $materials->refresh()->parent_id);
        $this->assertSame(25, Product::query()->where('category_id', $floorHeating->id)->count());
        $this->assertSame(7, Product::query()->where('category_id', $materials->id)->count());
    }

    private function category(string $name, string $slug, ?Category $parent = null): Category
    {
        return Category::query()->updateOrCreate(['slug' => $slug], [
            'name' => $name,
            'parent_id' => $parent?->id ?? 0,
            'is_active' => true,
        ]);
    }

    private function product(Category $category, string $sku): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'sku' => $sku,
            'slug' => 'migration-test-'.strtolower(str_replace('.', '-', $sku)),
            'name' => 'Тестовый товар '.$sku,
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
        ]);
    }
}

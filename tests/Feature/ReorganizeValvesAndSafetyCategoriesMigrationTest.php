<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReorganizeValvesAndSafetyCategoriesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_temporary_root_sections_and_classifies_all_valve_products(): void
    {
        $pipes = $this->category('Трубы и фитинги', 'truby-i-fitingi');
        $ballValves = $this->category('Шаровые краны', 'sharovye-krany');
        $ballValves->update(['is_active' => false]);
        $accessories = $this->category('Комплектующие', 'komplektuyushhie-dlya-otopleniya');
        $valves = $this->category('Краны и запорная арматура', 'krany-i-zapornaya-armatura');
        $mixing = $this->category('Смесительные клапаны и узлы', 'smesitelnye-klapany-i-uzly', $accessories);
        $radiator = $this->category('Радиаторная арматура', 'radiatornaya-armatura', $accessories);
        $regulating = $this->category('Предохранительная и регулирующая арматура', 'predokhranitelnaya-i-reguliruyushchaya-armatura', $accessories);
        $safety = $this->category('Группы безопасности', 'gruppy-bezopasnosti');

        $groups = [
            $mixing->id => ['PS-011.846'],
            $radiator->id => [
                'PS-004.867', 'PS-004.868', 'PS-000.18489', 'KOTLOV-003150',
                'KOTLOV-003149', 'KOTLOV-003140', 'KOTLOV-003131', 'PS-004.633',
                'KOTLOV-003087', 'KOTLOV-003088', 'PS-005.016', 'PS-004.617',
            ],
            $regulating->id => [
                'PS-006.170', 'PS-004.527', 'PS-006.308', 'PS-005.990',
                'PS-005.081', 'PS-003.963', 'PS-004.786', 'PS-004.784',
                'PS-004.785', 'PS-004.122', 'PS-006.172',
            ],
            $ballValves->id => [
                'PS-004.140', 'PS-004.779', 'PS-005.637', 'PS-006.061',
                'PS-004.783', 'PS-004.781', 'PS-004.782', 'PS-004.778',
                'PS-004.777', 'PS-004.151', 'PS-004.775', 'PS-004.139', 'PS-004.148',
            ],
        ];

        foreach (collect($groups)->flatten() as $sku) {
            $this->product($valves, $sku);
        }
        $unrelated = $this->product($valves, 'TEST-UNRELATED-VALVE');

        $migration = require database_path('migrations/2026_10_27_252000_reorganize_valves_and_safety_categories.php');
        $migration->up();

        foreach ($groups as $categoryId => $skus) {
            $this->assertSame(
                count($skus),
                Product::query()->where('category_id', $categoryId)->whereIn('sku', $skus)->count(),
            );
        }
        $this->assertSame($valves->id, $unrelated->refresh()->category_id);
        $this->assertSame($pipes->id, $valves->refresh()->parent_id);
        $this->assertSame($pipes->id, $ballValves->refresh()->parent_id);
        $this->assertTrue($ballValves->is_active);
        $this->assertSame($regulating->id, $safety->refresh()->parent_id);

        $migration->down();

        $this->assertSame(37, Product::query()->where('category_id', $valves->id)->whereIn('sku', collect($groups)->flatten())->count());
        $this->assertSame(0, $valves->refresh()->parent_id);
        $this->assertSame(0, $safety->refresh()->parent_id);
    }

    private function category(string $name, string $slug, ?Category $parent = null): Category
    {
        return Category::query()->create([
            'name' => $name,
            'slug' => $slug,
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

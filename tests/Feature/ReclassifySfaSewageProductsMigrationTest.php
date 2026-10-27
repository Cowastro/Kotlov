<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReclassifySfaSewageProductsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_keeps_safety_products_separate_and_moves_only_pumps_and_accessories(): void
    {
        $safety = $this->category('Группы безопасности', 'test-safety-groups');
        $sewage = $this->category('Канализационные насосы сухой установки', 'test-dry-sewage-pumps');
        $accessories = $this->category('Комплектующие для насосов', 'test-pump-accessories');

        $pump = $this->product($safety, 'KOTLOV-007922', 'SANIACCESS Pump');
        $alarm = $this->product($safety, 'KOTLOV-007928', 'SANIALARM');
        $realSafetyGroup = $this->product($safety, 'TEST-SAFETY-GROUP', 'Группа безопасности');

        $migration = require database_path('migrations/2026_10_27_235000_reclassify_sfa_sewage_products.php');
        $migration->up();

        $this->assertSame($sewage->id, $pump->refresh()->category_id);
        $this->assertSame($accessories->id, $alarm->refresh()->category_id);
        $this->assertSame($safety->id, $realSafetyGroup->refresh()->category_id);

        $migration->down();

        $this->assertSame($safety->id, $pump->refresh()->category_id);
        $this->assertSame($safety->id, $alarm->refresh()->category_id);
    }

    private function category(string $name, string $slug): Category
    {
        return Category::query()->firstOrCreate(
            ['name' => $name],
            ['slug' => $slug, 'parent_id' => 0, 'is_active' => true],
        );
    }

    private function product(Category $category, string $sku, string $name): Product
    {
        return Product::query()->updateOrCreate(
            ['sku' => $sku],
            [
                'category_id' => $category->id,
                'name' => $name,
                'slug' => 'migration-test-'.strtolower($sku),
                'price' => 100,
                'is_active' => true,
                'is_archived' => false,
            ],
        );
    }
}

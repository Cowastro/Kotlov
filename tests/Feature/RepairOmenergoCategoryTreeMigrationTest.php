<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairOmenergoCategoryTreeMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_omenergo_categories_are_moved_under_heating_components(): void
    {
        $parent = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Комплектующие',
            'slug' => 'komplektuyushhie-dlya-otopleniya',
            'is_active' => true,
        ]);

        $accessories = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Комплектующие для стабилизаторов',
            'slug' => 'komplektuyushchie-stabilizatorov',
            'is_active' => true,
        ]);

        $latr = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Автотрансформаторы ЛАТР',
            'slug' => 'avtotransformatory-latr',
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_10_17_070000_repair_omenergo_category_tree.php');
        $migration->up();
        $migration->up();

        $this->assertSame($parent->id, $accessories->fresh()->parent_id);
        $this->assertSame($parent->id, $latr->fresh()->parent_id);
    }
}

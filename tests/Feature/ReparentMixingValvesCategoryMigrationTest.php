<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReparentMixingValvesCategoryMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixing_valves_category_is_moved_from_drainage_pumps_to_heating_accessories(): void
    {
        $accessories = Category::query()->create([
            'name' => 'Комплектующие для отопления',
            'slug' => 'komplektuyushhie-dlya-otopleniya',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $drainage = Category::query()->create([
            'name' => 'Дренажные насосы',
            'slug' => 'drenazhnye-nasosy',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $mixingValves = Category::query()->create([
            'name' => 'Смесительные клапаны и узлы',
            'slug' => 'smesitelnye-klapany-i-uzly',
            'parent_id' => $drainage->id,
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_10_21_251000_reparent_mixing_valves_category.php');
        $migration->up();

        $this->assertSame($accessories->id, $mixingValves->fresh()->parent_id);
    }
}

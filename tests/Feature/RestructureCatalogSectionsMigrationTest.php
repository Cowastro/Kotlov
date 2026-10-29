<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RestructureCatalogSectionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_move_to_their_sections_and_duplicates_fold_with_a_redirect(): void
    {
        $kotly = Category::query()->create(['slug' => 'kotly', 'name' => 'Котлы', 'h1' => 'Котлы', 'parent_id' => 0, 'is_active' => true]);
        $accessories = Category::query()->create(['slug' => 'komplektuyushhie-dlya-otopleniya', 'name' => 'Комплектующие', 'parent_id' => 0, 'is_active' => true]);
        $pipes = Category::query()->create(['slug' => 'truby-i-fitingi', 'name' => 'Трубы и фитинги', 'parent_id' => 0, 'is_active' => true]);
        $floor = Category::query()->create(['slug' => 'teplyj-pol', 'name' => 'Электрический тёплый пол', 'parent_id' => 0, 'is_active' => true]);
        Category::query()->create(['slug' => 'klimat', 'name' => 'Климат', 'parent_id' => 0, 'is_active' => true]);
        $burners = Category::query()->create(['slug' => 'pelletnye-gorelki', 'name' => 'Пеллетные горелки', 'parent_id' => 0, 'is_active' => true]);
        $regulators = Category::query()->create(['slug' => 'regulyatory', 'name' => 'Автоматика', 'parent_id' => $accessories->id, 'is_active' => true]);
        $valves = Category::query()->create(['slug' => 'krany-i-zapornaya-armatura', 'name' => 'Краны и запорная арматура', 'parent_id' => $pipes->id, 'is_active' => true]);
        $ballValves = Category::query()->create(['slug' => 'sharovye-krany', 'name' => 'Шаровые краны', 'parent_id' => $pipes->id, 'is_active' => true]);
        $mats = Category::query()->create(['slug' => 'nagrevatelynye-maty', 'name' => 'Нагревательные маты', 'parent_id' => $floor->id, 'is_active' => true]);
        $duplicate = Category::query()->create(['slug' => 'nagrevatelnye-maty', 'name' => 'Нагревательные маты', 'parent_id' => $floor->id, 'is_active' => true]);
        $mat = Product::query()->create(['category_id' => $duplicate->id, 'name' => 'Мат 1 м²', 'slug' => 'mat-1', 'sku' => 'TEST-MAT', 'price' => 10, 'is_active' => true]);

        (require database_path('migrations/2026_10_30_100000_restructure_catalog_sections.php'))->up();

        $this->assertSame('Котлы и горелки', $kotly->fresh()->name);
        $this->assertSame('Котлы и горелки', $kotly->fresh()->h1);
        $this->assertSame(10, (int) $kotly->fresh()->sort_order);
        $this->assertSame($kotly->id, (int) $burners->fresh()->parent_id);
        $this->assertSame($kotly->id, (int) $regulators->fresh()->parent_id);
        $this->assertSame('Обвязка котельной', $accessories->fresh()->name);
        $this->assertSame(0, (int) $valves->fresh()->parent_id);
        $this->assertSame('Краны и арматура', $valves->fresh()->name);
        $this->assertSame($valves->id, (int) $ballValves->fresh()->parent_id);
        $this->assertSame('Тёплый пол', $floor->fresh()->name);
        $this->assertSame($mats->id, (int) $mat->fresh()->category_id);
        $this->assertFalse((bool) $duplicate->fresh()->is_active);
        $this->assertSame('/nagrevatelynye-maty', DB::table('redirects')->where('from_url', '/nagrevatelnye-maty')->value('to_url'));
        $this->assertDatabaseHas('categories', ['slug' => 'kondicionery', 'is_active' => true]);
    }
}

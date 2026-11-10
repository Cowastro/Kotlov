<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoilerCatalogNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_boilers_and_equipment_have_separate_catalog_sections_and_legacy_links_work(): void
    {
        $addCategory = fn (string $slug, string $name, ?Category $parent = null) => Category::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'parent_id' => $parent?->id ?? 0, 'is_active' => true],
        );
        $addProduct = fn (Category $category, string $slug, bool $stock = true) => Product::query()->create([
            'category_id' => $category->id, 'slug' => $slug, 'sku' => $slug, 'name' => $slug, 'price' => 100,
            'is_active' => true, 'in_stock' => $stock,
            'availability_status' => $stock ? Product::AVAILABILITY_IN_STOCK : Product::AVAILABILITY_CHECK,
        ]);

        $boilers = $addCategory('kotly', 'Котлы и горелки');
        $gas = $addCategory('gazovye', 'Газовые', $boilers);
        $electric = $addCategory('elektricheskie', 'Электрические', $boilers);
        $burners = $addCategory('pelletnye-gorelki', 'Пеллетные горелки', $boilers);
        $meters = $addCategory('schetchiki-gaza', 'Счётчики газа', $boilers);
        $ups = $addCategory('istochniki-besperebojnogo-pitaniya', 'ИБП', $boilers);
        $equipment = $addCategory('komplektuyushhie-dlya-otopleniya', 'Обвязка котельной');
        $controls = $addCategory('regulyatory', 'Терморегуляторы', $boilers);
        $columns = $addCategory('vodogreynaya-kolonka', 'Газовые колонки');

        $stocked = $addProduct($gas, 'test-gas-boiler');
        $onOrder = $addProduct($electric, 'test-electric-boiler', false);
        $addProduct($burners, 'test-pellet-burner');
        $addProduct($meters, 'test-gas-meter');
        $addProduct($ups, 'test-ups');
        $control = $addProduct($electric, 'sten-pue-15');
        $column = $addProduct($gas, 'ferroli-verona-11d');

        $migration = require database_path('migrations/2026_11_11_090000_separate_boilers_from_related_equipment.php');
        $migration->up();
        $migration->up();

        $this->assertSame(0, (int) $burners->fresh()->parent_id);
        $this->assertSame('gazovoe-oborudovanie', $meters->fresh()->parent->slug);
        $this->assertSame('elektropitanie-i-zashchita', $ups->fresh()->parent->slug);
        $this->assertSame($equipment->id, (int) $controls->fresh()->parent_id);
        $this->assertSame($controls->id, $control->fresh()->category_id);
        $this->assertSame($columns->id, $column->fresh()->category_id);

        $this->get('https://kotlov.by/kotly')->assertOk()
            ->assertSee('aria-label="Типы котлов"', false)
            ->assertSeeText('Котлы отопления')
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->sort()->values()->all() === [$stocked->id, $onOrder->id]);
        $this->get('https://kotlov.by/kotly?in_stock=1')->assertOk()
            ->assertSee('https://kotlov.by/gazovye?in_stock=1#catalog-products', false)
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$stocked->id]);
        $this->get('https://kotlov.by/kotly?subcategory=elektricheskie')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$onOrder->id]);
        $this->get('https://kotlov.by/kotly?subcategory=regulyatory&in_stock=1')
            ->assertStatus(301)
            ->assertRedirect('https://kotlov.by/regulyatory?in_stock=1');
        $this->get('https://kotlov.by/elektricheskie/sten-pue-15')
            ->assertStatus(301)
            ->assertRedirect('https://kotlov.by/regulyatory/sten-pue-15');
    }
}

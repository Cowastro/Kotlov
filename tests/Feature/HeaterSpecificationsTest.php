<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\HeaterCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaterSpecificationsTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $slug, ?Category $parent = null): Category
    {
        return Category::updateOrCreate(['slug' => $slug], ['name' => $slug, 'parent_id' => $parent?->id ?? 0, 'is_active' => true]);
    }

    private function product(Category $category, string $slug, array $extra = []): Product
    {
        return Product::create(['category_id' => $category->id, 'name' => $slug, 'slug' => $slug, 'sku' => $slug,
            'price' => 100, 'is_active' => true, 'is_archived' => false, 'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK, ...$extra]);
    }

    public function test_verified_exact_model_facts_override_legacy_values_without_power_area_estimates(): void
    {
        $catalog = app(HeaterCatalog::class);
        $product = new Product(['slug' => 'ballu-bohcl-11wrn', 'specs' => ['Мощность' => '99 кВт', 'Площадь обогрева' => '999 м²']]);
        $facts = $catalog->productFacts($product, 'maslyanye-obrevateli', $catalog->dimensions('maslyanye-obrevateli'));
        $this->assertSame('2,2 кВт', $facts['card']['Мощность обогрева']);
        $this->assertSame('до 27 м²', $facts['card']['Площадь обогрева']);
        $this->assertSame(['floor'], $facts['options']['mount']);
        $this->assertSame(['to-2-5'], $facts['options']['power']);
        $unknown = new Product(['slug' => 'unverified-heater', 'name' => 'Конвектор 2 кВт до 20 м²', 'specs' => ['Мощность' => '2 кВт']]);
        $this->assertSame([], $catalog->productFacts($unknown, 'obogrevateli', $catalog->dimensions('obogrevateli'))['card']);
    }

    public function test_declared_area_range_and_both_installations_are_preserved(): void
    {
        $catalog = app(HeaterCatalog::class);
        $product = new Product(['slug' => 'elektriceskii-konvektor-timberk-tecpf10n-dg-1500']);
        $facts = $catalog->productFacts($product, 'obogrevateli', $catalog->dimensions('obogrevateli'));
        $this->assertSame('15–18 м²', $facts['card']['Площадь обогрева']);
        $this->assertSame(['wall', 'floor'], $facts['options']['mount']);
        $this->assertSame(['to-20'], $facts['options']['area']);
    }

    public function test_catalog_filters_combine_and_use_the_standard_cards_and_selected_chips(): void
    {
        $root = $this->category('obogrevateli');
        $oil = $this->category('maslyanye-obrevateli', $root);
        $convectors = $this->category('elektricheskie-konvektoryi', $root);
        $radiator = $this->product($oil, 'ballu-bohcl-11wrn');
        $this->product($convectors, 'ballu-becevu-2000');
        $this->product($convectors, 'unknown-heater');
        $response = $this->get('/obogrevateli?heating[power]=to-2-5&heating[mount]=floor')->assertOk();
        $this->assertSame([$radiator->id], $response->viewData('products')->pluck('id')->all());
        $response->assertSee('Выбранные фильтры')->assertSee('rounded-pill')->assertSee('2,2 кВт')->assertSee('до 27 м²');
        $facets = $response->viewData('specificationFacets');
        $this->assertTrue($facets['power']['options']['to-2-5']['active']);
        $this->assertTrue($facets['mount']['options']['floor']['active']);
        $response->assertSee('noindex, follow');
    }

    public function test_product_details_do_not_duplicate_unverified_dimensions_and_migration_preserves_other_specs(): void
    {
        $category = $this->category('maslyanye-obrevateli');
        $product = $this->product($category, 'ballu-bohcl-11wrn', ['specs' => ['Мощность, Вт' => '99999', 'Монтаж' => 'потолочный', 'Цвет' => 'Белый']]);
        $migration = require database_path('migrations/2026_11_12_160000_verified_heater_specifications.php');
        $migration->up();
        $migration->up();
        $product->refresh();
        $this->assertSame('Белый', $product->specs['Цвет']);
        $this->assertSame('2,2 кВт', $product->specs['Мощность обогрева']);
        $this->assertCount(2, $product->documents);
        $this->get('/maslyanye-obrevateli/ballu-bohcl-11wrn')->assertOk()->assertSee('2,2 кВт')->assertDontSee('99999')->assertDontSee('потолочный');
    }

    public function test_every_published_fact_has_model_specific_evidence_and_valid_units(): void
    {
        foreach (HeaterCatalog::records() as $slug => $fact) {
            $this->assertNotEmpty($fact['source_model'], $slug);
            $this->assertStringStartsWith('https://', $fact['source']['url'], $slug);
            $this->assertGreaterThan(0, $fact['power_kw'], $slug);
            $this->assertGreaterThan(0, $fact['area_max_m2'], $slug);
            $this->assertEmpty(array_diff($fact['mount'], ['wall', 'floor']), $slug);
        }
    }
}

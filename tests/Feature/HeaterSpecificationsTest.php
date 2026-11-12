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
            if (isset($fact['area_max_m2'])) {
                $this->assertGreaterThan(0, $fact['area_max_m2'], $slug);
                $this->assertLessThanOrEqual($fact['area_max_m2'], $fact['area_min_m2'] ?? $fact['area_max_m2'], $slug);
            }
            $this->assertEmpty(array_diff($fact['mount'], ['wall', 'floor']), $slug);
        }
    }

    public function test_partial_passports_do_not_publish_legacy_or_estimated_area(): void
    {
        $category = $this->category('elektricheskie-konvektoryi');
        $catalog = app(HeaterCatalog::class);
        $product = $this->product($category, 'elektrokonvektor-elboom-ev1-usat1-15-230-ip20', [
            'specs' => ['Площадь обогрева' => 'до 15 м²', 'Цвет' => 'Белый'],
        ]);
        $facts = $catalog->productFacts($product, $category->slug, $catalog->dimensions($category->slug));
        $this->assertSame('1,5 кВт', $facts['card']['Мощность обогрева']);
        $this->assertSame(['wall', 'floor'], $facts['options']['mount']);
        $this->assertArrayNotHasKey('area', $facts['options']);
        $this->assertArrayNotHasKey('Площадь обогрева', $catalog->details($product, []));
        $this->get('/elektricheskie-konvektoryi?heating[area]=to-15')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->isEmpty());

        foreach (['hotta-aet-24-220-v-elektriceskii-teploakkumuliator', 'elektrokonvektor-elboom-ev1-usat-15-230-ip20'] as $slug) {
            $this->assertSame([], HeaterCatalog::verified(new Product(['slug' => $slug])));
        }
    }

    public function test_incremental_migration_writes_exact_ratings_preserves_specs_and_deduplicates_sources(): void
    {
        $category = $this->category('elektricheskie-konvektoryi');
        $product = $this->product($category, 'konvektor-elektriceskii-energolux-gchvi-25', ['specs' => ['Цвет' => 'Белый']]);
        $migration = require database_path('migrations/2026_11_12_170000_remaining_heater_manufacturer_facts.php');
        $migration->up();
        $migration->up();
        $product->refresh();
        $details = app(HeaterCatalog::class)->details($product, []);
        $this->assertSame('2,2 кВт', $details['Мощность обогрева']);
        $this->assertSame('2,5 кВт', $details['Потребляемая мощность']);
        $this->assertSame('до 30 м²', $details['Площадь обогрева']);
        $this->assertSame('Белый', $product->specs['Цвет']);
        $this->assertCount(2, $product->documents);
    }

    public function test_exact_manual_tables_and_audit_distinguish_partial_from_complete_models(): void
    {
        $records = HeaterCatalog::records();
        $this->assertSame(15, $records['royal-thermo-rchce-1502']['area_max_m2']);
        $this->assertSame(20, $records['royal-thermo-rchce-2002']['area_max_m2']);
        $this->assertSame(0.675, $records['teplyiy-plintus-elektricheskiy-mrtektum-smart-line-16m-belyiy-korichnevyiy']['power_kw']);
        $this->assertArrayNotHasKey('area_max_m2', $records['konvektor-elektriceskii-electrolux-echag2-1000-ef']);
        $this->assertSame(25, $records['elektrokonvektor-plintusnyi-electrolux-echag-2000-pi']['area_max_m2']);
        $audit = json_decode(file_get_contents(resource_path('data/heater-specification-audit.json')), true);
        $complete = collect($records)->filter(fn ($fact) => isset($fact['power_kw'], $fact['area_max_m2'], $fact['mount']))->count();
        $this->assertSame($complete, $audit['verified']);
        $this->assertSame(count($records) - $complete, $audit['partially_verified']);
        $this->assertSame($audit['reviewed'] - count($records), $audit['without_manufacturer_evidence']);
        foreach ($audit['unresolved'] as $row) {
            $fact = $records[$row['slug']] ?? [];
            $expected = [];
            foreach (['power_kw' => 'power_kw', 'area_m2' => 'area_max_m2', 'mount' => 'mount'] as $name => $key) {
                if (! isset($fact[$key])) $expected[] = $name;
            }
            $this->assertSame($expected, $row['missing'], $row['slug']);
        }
    }
}

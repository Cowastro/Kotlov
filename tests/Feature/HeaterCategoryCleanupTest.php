<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogPublishedCategories;
use App\Support\Catalog\HeaterCategoryClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class HeaterCategoryCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $slug, ?Category $parent = null): Category
    {
        return Category::updateOrCreate(['slug' => $slug], ['name' => $slug, 'is_active' => true, 'parent_id' => $parent?->id ?? 0]);
    }

    private function product(Category $category, string $name): Product
    {
        return Product::create(['category_id' => $category->id, 'name' => $name, 'slug' => 'heater-test-'.Product::count(),
            'sku' => 'HEATER-'.Product::count(), 'price' => 100, 'is_active' => true, 'is_archived' => false,
            'in_stock' => true, 'availability_status' => Product::AVAILABILITY_IN_STOCK]);
    }

    public function test_repair_separates_heaters_components_fans_and_chargers_and_preserves_legacy_urls(): void
    {
        $root = $this->category('obogrevateli');
        $oil = $this->category('maslyanye-obrevateli', $root);
        $convector = $this->category('elektricheskie-konvektoryi', $root);
        $fan = $this->category('ventilyatory');
        $electric = $this->category('elektropitanie-i-zashchita');
        $items = [];
        foreach (['Ballu BOH/CL-05WRN', 'Ballu BEC/EVU-2000', 'Ballu BFT/EVUR', 'Вентилятор промышленный Ballu BIF-4BB', 'Зарядная станция НЗС Компакт 7,4 G'] as $name) {
            $items[] = $this->product($root, $name);
        }
        $unrelated = $this->product($electric, 'Ballu BOH/CL-07WRN');
        $migration = require database_path('migrations/2026_11_12_150000_reclassify_heater_catalog.php');
        $migration->up();
        $migration->up();
        $expected = ['maslyanye-obrevateli', 'elektricheskie-konvektoryi', 'komplektuyushchie-dlya-obogrevateley', 'ventilyatory', 'zaryadnye-stantsii-dlya-elektromobiley'];
        foreach ($items as $index => $product) {
            $this->assertSame($expected[$index], $product->fresh()->category->slug);
            $this->assertEquals(100, $product->fresh()->price);
        }
        $this->assertEquals($electric->id, $unrelated->fresh()->category_id);
        $this->get('/obogrevateli/'.$items[0]->slug)->assertRedirect('/maslyanye-obrevateli/'.$items[0]->slug)->assertStatus(301);
    }

    public function test_empty_category_is_noindex_and_excluded_from_sitemap_until_stocked(): void
    {
        $root = $this->category('heater-hub-test');
        $empty = $this->category('heater-empty-test', $root);
        $this->get('/heater-empty-test')->assertOk()->assertSee('content="noindex, follow"', false);
        $this->get('https://kotlov.by/sitemap.xml')->assertOk()->assertDontSee('https://kotlov.by/heater-empty-test</loc>', false);
        $this->product($empty, 'Test heater');
        $this->assertContains($root->id, app(CatalogPublishedCategories::class)->ids());
        $this->get('/heater-empty-test')->assertOk()->assertDontSee('content="noindex, follow"', false);
    }

    public function test_heater_hub_unites_actual_heaters_and_keeps_components_out_of_product_grid(): void
    {
        $root = $this->category('obogrevateli');
        $oil = $this->category('maslyanye-obrevateli');
        $parts = $this->category('komplektuyushchie-dlya-obogrevateley', $root);
        $heater = $this->product($oil, 'Масляный радиатор для проверки');
        $component = $this->product($parts, 'Уникальное шасси для проверки');
        $migration = require database_path('migrations/2026_11_12_151000_group_heater_types_under_hub.php');
        $migration->up();
        $this->assertEquals($root->id, $oil->fresh()->parent_id);
        $response = $this->get('/obogrevateli')->assertOk();
        $this->assertTrue($response->viewData('products')->contains('id', $heater->id));
        $this->assertFalse($response->viewData('products')->contains('id', $component->id));
        $this->get('/komplektuyushchie-dlya-obogrevateley')->assertOk()->assertSee($component->name);
    }

    public function test_imports_use_specific_categories_and_do_not_confuse_water_convectors_or_boiler_parts(): void
    {
        $root = $this->category('obogrevateli');
        $oil = $this->category('maslyanye-obrevateli', $root);
        $gun = $this->category('teplovye-pushki', $root);
        $blower = $this->category('teploventilyatory', $root);
        $rus = app(\App\Console\Commands\SyncRusklimatCommand::class);
        $resolve = new ReflectionMethod($rus, 'resolveCategory');
        $this->assertEquals($oil->id, $resolve->invoke($rus, 'Тепловое оборудование', 'Ballu BOH/CL-07WRN'));
        $this->assertEquals($blower->id, $resolve->invoke($rus, 'teploventilyatory', 'Тепловой вентилятор тест'));
        $tep = app(\App\Console\Commands\ImportTeplodvorCatalogCommand::class);
        $detect = new ReflectionMethod($tep, 'detectCategory');
        $this->assertEquals($gun->id, $detect->invoke($tep, 'https://supplier.test/shop/obogrevateli/teplovye-pushki/model', 'Тестовая модель'));
        $this->assertNull(HeaterCategoryClassifier::slug('Водяной конвектор'));
        $this->assertNull(HeaterCategoryClassifier::slug('Блок управления газовым котлом'));
        $this->assertSame('komplektuyushchie-dlya-obogrevateley', HeaterCategoryClassifier::slug('Шасси для конвектора'));
    }
}

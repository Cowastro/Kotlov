<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\RadiatorCatalog;
use App\Support\Catalog\RadiatorCategoryClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RadiatorCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function categories(): array
    {
        $categories = [];
        foreach (RadiatorCategoryClassifier::CATEGORIES as $slug) {
            $categories[$slug] = Category::updateOrCreate(['slug' => $slug], ['name' => $slug, 'parent_id' => $categories['radiatory']->id ?? 0, 'is_active' => true]);
        }
        return $categories;
    }

    private function product(Category $category, string $slug, string $name, array $extra = []): Product
    {
        return Product::create(['category_id' => $category->id, 'name' => $name, 'slug' => $slug, 'sku' => $slug, 'price' => 100, 'is_active' => true,
            'is_archived' => false, 'in_stock' => true, 'availability_status' => Product::AVAILABILITY_IN_STOCK, ...$extra]);
    }

    public function test_imports_prefer_exact_material_and_keep_accessories_and_unknown_designs_separate(): void
    {
        $categories = $this->categories();
        $cases = [
            ['Радиатор Royal Thermo PianoForte Tower 300 Noir - 22 секц.', 'bimetallicheskie-radiatory'],
            ['Радиатор Лидея ЛК 11', 'stalnye-radiatory'],
            ['Радиатор Lammin ECO BM500/80', 'bimetallicheskie-radiatory'],
            ['Радиатор Lammin ECO AL500/80', 'alyuminievye-radiatory'],
            ['Монтажный комплект для радиатора', 'radiatornaya-armatura'],
            ['Пробка радиатора', 'radiatornaya-armatura'],
            ['Водяной конвектор Kermi KVN', 'vodyanye-konvektory'],
            ['Радиатор стальной с крепежом в комплекте', 'stalnye-radiatory'],
            ['Fondital ALUSTAL BIMETAL SUPER 500/100 - 12 сек.', 'bimetallicheskie-radiatory'],
            ['Buderus BH1-W0 K белый', 'radiatornaya-armatura'],
            ['Buderus 22/500/1200 VK-Profil', 'stalnye-radiatory'],
        ];
        $rus = app(\App\Console\Commands\SyncRusklimatCommand::class);
        $tep = app(\App\Console\Commands\ImportTeplodvorCatalogCommand::class);
        foreach ($cases as [$name, $slug]) {
            $this->assertSame($slug, RadiatorCategoryClassifier::slug($name, 'Алюминиевые радиаторы'), $name);
            $this->assertEquals($categories[$slug]->id, (new \ReflectionMethod($rus, 'resolveCategory'))->invoke($rus, 'Алюминиевые радиаторы', $name));
            $this->assertEquals($categories[$slug]->id, (new \ReflectionMethod($tep, 'detectCategory'))->invoke($tep, 'https://supplier.test/raditory/allyminevye/model', $name));
        }
        $this->assertNull(RadiatorCategoryClassifier::slug('Масляный радиатор Ballu BOH/CL-07WRN'));
        $this->assertEquals($categories['radiatory']->id, (new \ReflectionMethod($tep, 'detectCategory'))->invoke($tep, 'https://supplier.test/raditory/dizaynerskie-radiatory/model', 'Дизайн-радиатор неизвестной серии'));
        $thermo = app(\App\Console\Commands\SyncThermostudioPricelistCommand::class);
        (new \ReflectionProperty($thermo, 'categoryBySlug'))->setValue($thermo, collect($categories)->map->id->all());
        $method = new \ReflectionMethod($thermo, 'categoryId');
        $this->assertEquals($categories['alyuminievye-radiatory']->id, $method->invoke($thermo, ['name' => 'Радиатор алюминиевый Rifar Alum', 'section' => 'Стальные радиаторы']));
        $this->assertEquals($categories['radiatory']->id, $method->invoke($thermo, ['name' => 'Радиатор неизвестный', 'section' => '', 'description' => '']));
    }

    public function test_verified_output_keeps_temperature_difference_and_never_estimates_room_area(): void
    {
        $catalog = app(RadiatorCatalog::class);
        $record = collect(RadiatorCatalog::records())->first(fn ($row) => isset($row['power_dt70_w']));
        $slug = array_search($record, RadiatorCatalog::records(), true);
        $product = new Product(['slug' => $slug, 'name' => $record['name'], 'specs' => ['Площадь обогрева' => '999 м²', 'Мощность' => '1026 Вт']]);
        $facts = $catalog->productFacts($product, 'radiatory', $catalog->dimensions('radiatory'));
        $this->assertSame($record['power_dt70_w'].' Вт', $facts['card']['Теплоотдача при ΔT 70 °C']);
        $this->assertArrayNotHasKey('Площадь обогрева', $facts['card']);
        $tower = collect(RadiatorCatalog::records())->first(fn ($row) => str_contains($row['name'], 'Tower'));
        $this->assertArrayNotHasKey('power_dt70_w', $tower);
        $product->name = 'Другой радиатор';
        $this->assertSame([], RadiatorCatalog::verified($product));
    }

    public function test_catalog_filters_use_standard_chips_and_exclude_parts_from_the_hub(): void
    {
        $categories = $this->categories();
        $record = collect(RadiatorCatalog::records())->first(fn ($row) => isset($row['power_dt70_w']));
        $slug = array_search($record, RadiatorCatalog::records(), true);
        $radiator = $this->product($categories['bimetallicheskie-radiatory'], $slug, $record['name']);
        $part = $this->product($categories['radiatornaya-armatura'], 'radiator-part', 'Монтажный комплект');
        $this->product($categories['stalnye-radiatory'], 'unknown-radiator', 'Неизвестный радиатор');
        $response = $this->get('/radiatory?radiator[material]=bimetal')->assertOk();
        $this->assertSame([$radiator->id], $response->viewData('products')->pluck('id')->all());
        $response->assertSee('Выбранные фильтры')->assertSee('rounded-pill')->assertSee('noindex, follow')->assertSee('ΔT');
        $hub = $this->get('/radiatory')->assertOk();
        $this->assertFalse($hub->viewData('products')->contains('id', $part->id));
        $this->get('/radiatornaya-armatura')->assertOk()->assertSee($part->name);
    }

    public function test_repair_preserves_identity_and_prices_and_old_urls_redirect_then_rollback_restores_data(): void
    {
        Storage::fake('local');
        $categories = $this->categories();
        $record = collect(RadiatorCatalog::records())->first(fn ($row) => str_contains($row['name'], 'Tower'));
        $slug = array_search($record, RadiatorCatalog::records(), true);
        $radiator = $this->product($categories['alyuminievye-radiatory'], $slug, $record['name'], ['specs' => ['Мощность' => '1026 Вт', 'Цвет' => 'Чёрный'], 'short_description' => '1026 Вт, высота 574 мм']);
        $migration = require database_path('migrations/2026_11_12_210000_repair_radiator_catalog.php');
        $migration->up();
        $migration->up();
        $radiator->refresh();
        $this->assertEquals($categories['bimetallicheskie-radiatory']->id, $radiator->category_id);
        $this->assertEquals(100, $radiator->price);
        $this->assertSame('Чёрный', $radiator->specs['Цвет']);
        $this->assertArrayNotHasKey('Мощность', $radiator->specs);
        $this->assertStringNotContainsString('1026', $radiator->short_description);
        $this->get('/alyuminievye-radiatory/'.$slug)->assertStatus(301)->assertRedirect('/bimetallicheskie-radiatory/'.$slug);
        $this->get('/bimetallicheskie-radiatory/'.$slug)->assertOk()->assertSee('Документация производителя')->assertDontSee('1026 Вт');
        $migration->down();
        $this->assertEquals($categories['alyuminievye-radiatory']->id, $radiator->fresh()->category_id);
        $this->assertSame('1026 Вт, высота 574 мм', $radiator->fresh()->short_description);
    }
}

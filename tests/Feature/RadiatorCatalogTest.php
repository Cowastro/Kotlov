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
            ['Радиатор панельный Royal Thermo COMPACT C22-500-1600 RAL9016', 'stalnye-radiatory'],
            ['Радиатор Royal Thermo Monoblock B 100 500', 'bimetallicheskie-radiatory'],
            ['Радиатор Royal Thermo DreamLiner 500', 'alyuminievye-radiatory'],
            ['Радиатор Royal Thermo Revolution 350', 'alyuminievye-radiatory'],
            ['Радиатор Royal Thermo Indigo 500', 'alyuminievye-radiatory'],
        ];
        $rus = app(\App\Console\Commands\SyncRusklimatCommand::class);
        $tep = app(\App\Console\Commands\ImportTeplodvorCatalogCommand::class);
        foreach ($cases as [$name, $slug]) {
            $this->assertSame($slug, RadiatorCategoryClassifier::slug($name, 'Алюминиевые радиаторы'), $name);
            $this->assertEquals($categories[$slug]->id, (new \ReflectionMethod($rus, 'resolveCategory'))->invoke($rus, 'Алюминиевые радиаторы', $name));
            $this->assertEquals($categories[$slug]->id, (new \ReflectionMethod($tep, 'detectCategory'))->invoke($tep, 'https://supplier.test/raditory/allyminevye/model', $name));
        }
        $this->assertNull(RadiatorCategoryClassifier::slug('Масляный радиатор Ballu BOH/CL-07WRN'));
        $this->assertEquals($categories['radiatornaya-armatura']->id, (new \ReflectionMethod($tep, 'detectCategory'))->invoke($tep, 'https://supplier.test/raditory/komplektuyuschie-k-radiatoram/model', 'Переходник 1/2'));
        $this->assertEquals($categories['radiatory']->id, (new \ReflectionMethod($tep, 'detectCategory'))->invoke($tep, 'https://supplier.test/raditory/dizaynerskie-radiatory/model', 'Дизайн-радиатор неизвестной серии'));
        $thermo = app(\App\Console\Commands\SyncThermostudioPricelistCommand::class);
        (new \ReflectionProperty($thermo, 'categoryBySlug'))->setValue($thermo, collect($categories)->map->id->all());
        $method = new \ReflectionMethod($thermo, 'categoryId');
        $this->assertEquals($categories['alyuminievye-radiatory']->id, $method->invoke($thermo, ['name' => 'Радиатор алюминиевый Rifar Alum', 'section' => 'Стальные радиаторы']));
        $this->assertEquals($categories['radiatory']->id, $method->invoke($thermo, ['name' => 'Радиатор неизвестный', 'section' => '', 'description' => '']));
    }

    public function test_remaining_named_families_leave_the_root_and_rollback_preserves_later_manual_changes(): void
    {
        Storage::fake('local');
        $categories = $this->categories();
        $panel = $this->product($categories['radiatory'], 'compact-family', 'Радиатор панельный Royal Thermo COMPACT C22-500-1600 RAL9016');
        $aluminium = $this->product($categories['radiatory'], 'dream-family', 'Радиатор Royal Thermo DreamLiner 500');
        $indigo = $this->product($categories['alyuminievye-radiatory'], 'radiator-royal-thermo-indigo-500', 'Радиатор Royal Thermo Indigo 500', ['short_description' => 'Радиатор — это высокоэффективный биметаллический прибор.']);
        $migration = require database_path('migrations/2026_11_12_211000_finish_radiator_model_classification.php');
        $migration->up();
        $migration->up();
        $this->assertEquals($categories['stalnye-radiatory']->id, $panel->fresh()->category_id);
        $this->assertEquals($categories['alyuminievye-radiatory']->id, $aluminium->fresh()->category_id);
        $this->assertStringNotContainsString('биметаллический', $indigo->fresh()->short_description);
        $this->assertSame('Алюминий', $indigo->fresh()->specs['Материал']);
        $panel->update(['category_id' => $categories['bimetallicheskie-radiatory']->id]);
        $migration->down();
        $this->assertEquals($categories['bimetallicheskie-radiatory']->id, $panel->fresh()->category_id);
        $this->assertEquals($categories['radiatory']->id, $aluminium->fresh()->category_id);
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
        $radiator = $this->product($categories['alyuminievye-radiatory'], $slug, $record['name'], ['specs' => ['Мощность' => '1026 Вт', 'Цвет' => 'Чёрный', 'Давление опрессовки' => '999 бар', 'Вес упаковки' => 'кг'], 'short_description' => '1026 Вт, высота 574 мм']);
        $migration = require database_path('migrations/2026_11_12_210000_repair_radiator_catalog.php');
        $migration->up();
        $migration->up();
        $radiator->refresh();
        $this->assertEquals($categories['bimetallicheskie-radiatory']->id, $radiator->category_id);
        $this->assertEquals(100, $radiator->price);
        $this->assertSame('Чёрный', $radiator->specs['Цвет']);
        $this->assertArrayNotHasKey('Мощность', $radiator->specs);
        $this->assertArrayNotHasKey('Давление опрессовки', $radiator->specs);
        $this->assertArrayNotHasKey('Вес упаковки', $radiator->specs);
        $this->assertStringNotContainsString('1026', $radiator->short_description);
        $this->get('/alyuminievye-radiatory/'.$slug)->assertStatus(301)->assertRedirect('/bimetallicheskie-radiatory/'.$slug);
        $this->get('/bimetallicheskie-radiatory/'.$slug)->assertOk()->assertSee('Документация производителя')->assertDontSee('1026 Вт');
        $migration->down();
        $this->assertEquals($categories['alyuminievye-radiatory']->id, $radiator->fresh()->category_id);
        $this->assertSame('1026 Вт, высота 574 мм', $radiator->fresh()->short_description);
    }

    public function test_public_table_body_quick_view_and_metadata_use_evidence_without_rewriting_raw_storage(): void
    {
        $categories = $this->categories();
        $slug = 'radiator-panelnyi-royal-thermo-compact-c22-500-1600-ral9016';
        $record = RadiatorCatalog::records()[$slug];
        $legacy = ['Тип радиатора' => '11', 'Толщина' => '47 мм', 'Цвет' => 'Белый'];
        $product = $this->product($categories['stalnye-radiatory'], $slug, $record['name'], [
            'specs' => $legacy, 'short_description' => 'LEGACY_WRONG_MARKER 47 мм, тип 11',
            'content' => '<p>LEGACY_WRONG_MARKER 47 мм</p>', 'meta_description' => 'LEGACY_WRONG_MARKER 47 мм',
        ]);
        $response = $this->get('/stalnye-radiatory/'.$slug)->assertOk()->assertDontSee('LEGACY_WRONG_MARKER')->assertDontSee('47 мм');
        $this->assertSame('22', $response->viewData('fireplaceSpecs')['Тип панельного радиатора']);
        $this->assertSame('500 мм', $response->viewData('fireplaceSpecs')['Высота']);
        $this->assertArrayNotHasKey('Тип радиатора', $response->viewData('fireplaceSpecs'));
        $this->get('/stalnye-radiatory?radiator[panel_type]=22')->assertOk()->assertDontSee('LEGACY_WRONG_MARKER')
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$product->id]);
        $this->get('/stalnye-radiatory?radiator[panel_type]=11')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->isEmpty());
        $this->assertEquals($legacy, $product->fresh()->specs);
        $this->assertSame('<p>LEGACY_WRONG_MARKER 47 мм</p>', $product->fresh()->content);
    }
}

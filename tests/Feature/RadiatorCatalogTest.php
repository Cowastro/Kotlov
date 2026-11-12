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
        $tower = RadiatorCatalog::records()['radiator-royal-thermo-piano-forte-tower-500'];
        $this->assertArrayNotHasKey('power_dt70_w', $tower);
        $product->name = 'Другой радиатор';
        $this->assertSame([], RadiatorCatalog::verified($product));
    }

    public function test_catalog_filters_use_standard_chips_and_exclude_parts_from_the_hub(): void
    {
        $categories = $this->categories();
        $record = collect(RadiatorCatalog::records())->first(fn ($row) => isset($row['power_dt70_w']) && ($row['material'] ?? null) === 'bimetal');
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

    public function test_manufacturer_tables_keep_section_outputs_out_of_whole_radiator_filters(): void
    {
        $categories = $this->categories();
        $catalog = app(RadiatorCatalog::class);
        $slug = 'radiator-stalnoi-trubcatyi-zehnder-charleston-2035-62-342';
        $row = RadiatorCatalog::records()[$slug];
        $product = $this->product($categories['stalnye-radiatory'], $slug, $row['name']);
        $details = $catalog->details($product, []);
        $this->assertSame('342 мм', $details['Высота']);
        $this->assertSame('62 мм', $details['Глубина']);
        $this->assertSame('27,5 Вт/секц.', $details['Теплоотдача секции при ΔT 50 °C']);
        $this->assertSame('42 Вт/секц.', $details['Теплоотдача секции при ΔT 70 °C']);
        $this->assertArrayNotHasKey('Ширина', $details);
        $this->assertArrayNotHasKey('Теплоотдача при ΔT 70 °C', $details);
        $facts = $catalog->productFacts($product, 'radiatory', $catalog->dimensions('radiatory'));
        $this->assertArrayNotHasKey('power70', $facts['options']);
        $this->assertArrayNotHasKey('power50', $facts['options']);
        $html = view('partials.fireplace-card-specs', ['fireplaceCardSpecs' => $facts['card']])->render();
        $this->assertStringContainsString('42 Вт/секц. · ΔT 70 °C', $html);
        $this->get('/stalnye-radiatory?radiator[power70]=to-1000')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->isEmpty());
        $this->get('/stalnye-radiatory/'.$slug)->assertOk()->assertSee('Исполнение подключения')
            ->assertSee('27,5 Вт/секц.')->assertSee('Документация производителя');
        $only50 = collect(RadiatorCatalog::records())->first(fn ($row) => isset($row['section_power_dt50_w']) && ! isset($row['section_power_dt70_w']));
        $only50Slug = array_search($only50, RadiatorCatalog::records(), true);
        $lowTemperature = new Product(['slug' => $only50Slug, 'name' => $only50['name']]);
        $lowFacts = $catalog->productFacts($lowTemperature, 'radiatory', $catalog->dimensions('radiatory'));
        $lowHtml = view('partials.fireplace-card-specs', ['fireplaceCardSpecs' => $lowFacts['card']])->render();
        $this->assertStringContainsString('Вт/секц. · ΔT 50 °C', $lowHtml);
        $this->assertStringNotContainsString('ΔT 70 °C', $lowHtml);
        $this->assertArrayNotHasKey('power50', $lowFacts['options']);
    }

    public function test_exact_factory_revisions_and_connection_codes_are_preserved(): void
    {
        $r = RadiatorCatalog::records();
        $this->assertEquals(1156, $r['kermi-ftv110500901r2y-radiator-stalnoi-niznee-rf']['power_dt70_w']);
        $this->assertSame('bottom', $r['kermi-ftv110500901r2y-radiator-stalnoi-niznee-rf']['connection']);
        $this->assertEquals(3599, $r['radiator-panelnyi-royal-thermo-compact-c22-500-1600-ral9016']['power_dt70_w']);
        $this->assertEquals(97, $r['radiator-panelnyi-royal-thermo-compact-c22-500-1600-ral9016']['depth_mm']);
        $tubog = $r['stalnoi-trubcatyi-radiator-rifar-tubog-2180-12-d1'];
        $this->assertSame('bottom', $tubog['connection']);
        $this->assertEquals(558, $tubog['width_mm']);
        $this->assertEquals(198, $tubog['section_power_dt70_w']);
        $this->assertArrayNotHasKey('power_dt70_w', $tubog);
        $this->assertEquals(197, $r['radiator-bimetalliceskii-rifar-base-500']['section_power_dt70_w']);
        $this->assertArrayNotHasKey('width_mm', $r['radiator-bimetalliceskii-rifar-base-500']);
        $this->assertEquals(566, $r['radiator-stalnoi-trubcatyi-zehnder-charleston-3057-100-566']['height_mm']);
        foreach (['radiator-kermi-fko-224004800', 'radiator-stalnoi-kermi-line-plk-125051305', 'kermi-fko-110504'] as $slug) {
            $this->assertArrayNotHasKey('power_dt70_w', $r[$slug], $slug);
        }
        foreach ($r as $slug => $row) {
            foreach ($row['evidence'] ?? [] as $evidence) {
                $this->assertContains($evidence['source'], $row['sources'], $slug);
                foreach ($evidence['fields'] as $field) $this->assertArrayHasKey($field, $row, $slug);
            }
            foreach ($row as $field => $value) {
                if (preg_match('/^(?:section_)?(?:power_dt\d+_w|width_mm|height_mm|depth_mm|centres_mm)$/', $field)) {
                    $this->assertGreaterThan(0, $value, $slug.':'.$field);
                }
            }
        }
    }

    public function test_kvn_declared_regime_is_visible_and_filterable_without_inventing_delta70(): void
    {
        $categories = $this->categories();
        $slug = 'konvektor-vnutripolnyi-kermi-kvn-230x80x1500';
        $row = RadiatorCatalog::records()[$slug];
        $product = $this->product($categories['vodyanye-konvektory'], $slug, $row['name']);
        $unknown = $this->product($categories['vodyanye-konvektory'], 'unknown-convector', 'Конвектор без подтверждения');
        $catalog = app(RadiatorCatalog::class);
        $facts = $catalog->productFacts($product, 'vodyanye-konvektory', $catalog->dimensions('vodyanye-konvektory'));
        $this->assertSame(['to-1000'], $facts['options']['power60']);
        $this->assertArrayNotHasKey('power70', $facts['options']);
        $this->assertArrayNotHasKey('power50', $facts['options']);
        $html = view('partials.fireplace-card-specs', ['fireplaceCardSpecs' => $facts['card']])->render();
        $this->assertStringContainsString('512 Вт · ΔT 60 °C', $html);
        $this->assertStringNotContainsString('ΔT 70 °C', $html);
        $this->get('/vodyanye-konvektory?radiator[power60]=to-1000')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$product->id])
            ->assertSee('512 Вт · ΔT 60 °C')->assertSee('Сбросить все фильтры');
        $this->get('/vodyanye-konvektory/'.$slug)->assertOk()->assertSee('512 Вт')
            ->assertSee('Теплоотдача при ΔT 60 °C')->assertSee('Документация производителя');
    }

    public function test_remaining_passports_preserve_colours_series_and_temperature_heads(): void
    {
        $r = RadiatorCatalog::records();
        $this->assertEquals(1870, $r['radiator-royal-thermo-pianoforte-tower-500-newsilver-satin-22-sekc']['power_dt70_w']);
        $this->assertEquals(1968, $r['radiator-royal-thermo-pianoforte-tower-500-newnoir-sable-22-sekc']['power_dt70_w']);
        $this->assertEquals(1010, $r['radiator-royal-thermo-pianoforte-tower-200-silver-satin-22-sekc']['power_dt70_w']);
        $this->assertEquals(1050, $r['radiator-royal-thermo-pianoforte-tower-200-bianco-traffico-22-sekc']['power_dt70_w']);
        $this->assertArrayNotHasKey('height_mm', $r['radiator-royal-thermo-pianoforte-tower-500-newsilver-satin-22-sekc']);
        $this->assertArrayNotHasKey('power_dt70_w', $r['radiator-royal-thermo-piano-forte-tower-500']);
        $this->assertEquals(336, $r['kermi-fko-110504']['power_dt50_w']);
        $this->assertArrayNotHasKey('power_dt70_w', $r['kermi-fko-110504']);
        $this->assertEquals(977, $r['radiator-stalnoi-kermi-line-plk-115051305']['power_dt50_w']);
        $this->assertEquals(1245, $r['radiator-stalnoi-kermi-line-plk-125051305']['power_dt50_w']);
        $this->assertEquals(1584, $r['radiator-bimetalliceskii-termica-bitherm-500100-12-sekcii']['power_dt70_w']);
        $this->assertEquals(115, $r['radiator-bimetalliceskii-royal-thermo-infinity-300-silver-satin-10-sekc']['section_power_dt70_w']);
        $this->assertEquals(181, $r['radiator-aliuminievyi-royal-thermo-monoblock-a-500-20-12-sekc']['section_power_dt70_w']);
        $this->assertArrayNotHasKey('power_dt70_w', $r['radiator-aliuminievyi-royal-thermo-monoblock-a-500-20-12-sekc']);
        $audit = json_decode(file_get_contents(resource_path('data/radiator-thermal-completion-audit.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(218, $audit['products']);
        $this->assertSame(118, $audit['confirmed']);
        $this->assertSame(98, $audit['unconfirmed_heating_appliances']);
        foreach ($audit['products'] as $item) {
            $record = $r[$item['slug']] ?? [];
            $thermal = array_filter($record, fn ($field) => str_starts_with($field, 'power_dt') || str_starts_with($field, 'section_power_dt'), ARRAY_FILTER_USE_KEY);
            if ($item['status'] !== 'confirmed') {
                $this->assertSame([], $thermal, $item['slug']);
                continue;
            }
            $this->assertEquals($item['fields'], $thermal, $item['slug']);
            $this->assertContains($item['source'], $record['sources'], $item['slug']);
        }
        $categories = $this->categories();
        $slug = 'radiator-aliuminievyi-royal-thermo-monoblock-a-500-20-12-sekc';
        $product = $this->product($categories['alyuminievye-radiatory'], $slug, $r[$slug]['name']);
        $description = app(RadiatorCatalog::class)->cleanDescription($product);
        $this->assertStringContainsString('181 Вт/секц.', $description['short_description']);
        $this->assertStringNotContainsString('секц..', $description['short_description']);
    }
}

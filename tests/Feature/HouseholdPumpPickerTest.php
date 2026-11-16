<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\HouseholdPumpFacts;
use App\Support\Catalog\PumpCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HouseholdPumpPickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function verified(string $model): Product
    {
        $records = HouseholdPumpFacts::records();
        $id = array_key_first(array_filter($records['products'], fn ($binding) => $binding['model'] === $model));
        $binding = $records['products'][$id];
        $category = Category::firstOrCreate(['slug' => $binding['category']], ['name' => PumpCatalog::NAMES[$binding['category']], 'is_active' => true, 'parent_id' => 0]);
        $product = new Product(['name' => $binding['name'], 'slug' => 'exact-pump-'.$id, 'sku' => 'TEST-'.$id,
            'category_id' => $category->id, 'is_active' => true, 'is_archived' => false, 'price' => 100,
            'specs' => ['Максимальный напор, м' => 999, 'Максимальный расход, м³/ч' => 999]]);
        $product->forceFill(['id' => (int) $id])->save();
        return $product;
    }

    public function test_bore_size_does_not_use_outer_pump_diameter_and_combines_with_ranges(): void
    {
        $slim = $this->verified('ECO SLIM 1-28'); // body 70mm, minimum bore 75mm
        $this->verified('MINI ECO 1-35'); // body 75mm, minimum bore 85mm
        $this->get('https://kotlov.by/skvajinnye-nasosy?pump[bore]=75&pump_head=30&pump_head_to=40&pump[voltage]=220')
            ->assertOk()->assertViewHas('products', fn ($products) => $products->modelKeys() === [$slim->id])
            ->assertSee('Скважина от')->assertSee('75 мм')->assertSee('33 м')
            ->assertSee('Подбор скважинного насоса')->assertSee('noindex, follow');
    }

    public function test_surface_suction_and_station_tank_come_from_the_exact_configuration(): void
    {
        $jet = $this->verified('JET 60 S');
        $this->verified('QB 60');
        $this->get('https://kotlov.by/poverhnostnyie?pump[suction]=8&q=JET&price_max=150')
            ->assertOk()->assertViewHas('products', fn ($products) => $products->modelKeys() === [$jet->id])
            ->assertSee('Подбор поверхностного насоса')->assertSee('33 м')->assertDontSee('999 м');
        $station = $this->verified('AUTO JET 60 S-5');
        $this->verified('AUTO JET 60 S'); // tank cannot be established from this title alone
        $this->get('https://kotlov.by/nasosnyie-stantsii?pump[tank]=5&pump[suction]=8')
            ->assertOk()->assertViewHas('products', fn ($products) => $products->modelKeys() === [$station->id])
            ->assertSee('Подбор насосной станции')->assertSee('Гидроаккумулятор');
    }

    public function test_drainage_solids_and_immersion_use_maximum_not_nominal_head_or_fibre_size(): void
    {
        $wq = $this->verified('50WQ10-10-0.75');
        $this->verified('FEKACUT V750DF'); // free hard particles 9mm, NOT 30mm cuttable material
        $this->get('https://kotlov.by/drenajnyie?pump[solids]=35&pump[immersion]=20&pump[voltage]=380')
            ->assertOk()->assertViewHas('products', fn ($products) => $products->modelKeys() === [$wq->id])
            ->assertSee('Подбор дренажного насоса')->assertSee('Твёрдые частицы до');
        $this->get('https://kotlov.by/drenajnyie?pump_head=1')->assertOk()
            ->assertViewHas('products', fn ($products) => ! $products->contains('id', $wq->id));
    }

    public function test_unverified_imports_remain_searchable_but_do_not_supply_technical_filters(): void
    {
        $p = $this->verified('JET 60 S');
        $p->update(['name' => 'Unknown replacement']);
        $this->get('https://kotlov.by/poverhnostnyie?q=Unknown')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$p->id])
            ->assertSee('Уточняются');
        $this->get('https://kotlov.by/poverhnostnyie?pump_head=1')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->isEmpty());
        $this->get('https://kotlov.by/poverhnostnyie?pump[verified]=no')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$p->id]);
        $this->get('https://kotlov.by/poverhnostnyie?attr[4][]=2&q=Unknown&in_stock=1')
            ->assertRedirect('https://kotlov.by/poverhnostnyie?q=Unknown&in_stock=1#pump-picker');
    }

    public function test_manufacturer_registry_rejects_renamed_and_moved_products_and_has_evidence(): void
    {
        $p = $this->verified('JET 60 S');
        $catalog = new PumpCatalog;
        $fact = $catalog->productFacts($p, 'poverhnostnyie', $catalog->dimensions('poverhnostnyie'));
        $this->assertSame(33, $fact['numbers']['head']);
        $this->assertNull(HouseholdPumpFacts::verified($p, 'nasosnyie-stantsii'));
        $records = HouseholdPumpFacts::records();
        foreach ($records['products'] as $binding) {
            $model = $records['models'][$binding['model']];
            $this->assertNotEmpty($model['pages']);
            $source = $model['source'] ?? $records['source'];
            $this->assertContains(parse_url($source['url'], PHP_URL_HOST), ['unipump.ru', 'wellmix-pump.ru', 'imp-pumps.com', 'jeelex.ru', 'www.puritypumps.com', 'www.dabpumps.com', 'www.koshin-ltd.jp']);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $source['sha256']);
            foreach ($model['numbers'] as $field => $value) {
                $this->assertGreaterThan(0, $value, $binding['model'].' '.$field);
                $this->assertArrayNotHasKey($field, $model['conflicts'] ?? []);
            }
            $this->assertContains($binding['category'], PumpCatalog::MANUFACTURER_CATEGORIES);
        }
    }

    public function test_nominal_working_point_and_free_passage_do_not_supply_maximum_filters(): void
    {
        $p = $this->verified('WELLMIX 80WQ2-50-25-7.5/2_380V');
        $catalog = new PumpCatalog;
        $facts = $catalog->productFacts($p, 'drenajnyie', $catalog->dimensions('drenajnyie'));
        $this->assertSame(50.0, $facts['numbers']['rated_flow']);
        $this->assertSame(25.0, $facts['numbers']['rated_head']);
        $this->assertArrayNotHasKey('head', $facts['numbers']);
        $this->assertArrayNotHasKey('flow', $facts['numbers']);
        $this->assertArrayNotHasKey('solids', $facts['options']);
        $this->get('https://kotlov.by/drenajnyie?q=WELLMIX')->assertOk()
            ->assertSee('Номин. напор')->assertSee('Номин. расход')->assertSee('Свободный проход');
        foreach (['pump_head=1', 'pump_flow=1', 'pump[solids]=5'] as $query) {
            $this->get('https://kotlov.by/drenajnyie?'.$query)->assertOk()
                ->assertViewHas('products', fn ($products) => $products->isEmpty());
        }
    }

    public function test_product_details_use_the_same_evidence_as_the_picker_instead_of_imported_claims(): void
    {
        $p = $this->verified('JET 60 S');
        $this->get('https://kotlov.by/poverhnostnyie/'.$p->slug)->assertOk()
            ->assertViewHas('fireplaceSpecs', fn ($rows) => $rows['Макс. напор'] === '33 м')
            ->assertSee('Номинальные и табличные значения показаны отдельно')
            ->assertSee('Katalog-2026', false)->assertDontSee('999 м');
    }

    public function test_conflicting_immersion_does_not_enter_filters_and_is_explained_in_details(): void
    {
        $p = $this->verified('MINI ECO 1-35');
        $catalog = new PumpCatalog;
        $facts = $catalog->productFacts($p, 'skvajinnye-nasosy', $catalog->dimensions('skvajinnye-nasosy'));
        $this->assertArrayNotHasKey('immersion', $facts['numbers']);
        $this->assertArrayNotHasKey('immersion', $facts['options']);
        $this->assertStringContainsString('документов производителя различаются', $facts['details']['Макс. погружение']);
    }

    public function test_new_passport_maxima_are_not_taken_from_the_model_designation(): void
    {
        $pump = $this->verified('UNIPUMP USP 12-30-3.7');
        $catalog = new PumpCatalog;
        $facts = $catalog->productFacts($pump, 'drenajnyie', $catalog->dimensions('drenajnyie'));
        // USP passport physical page 5: model digits describe nominal Q/H;
        // the separate maximum rows state 33 m³/h and 34 m.
        $this->assertSame(33, $facts['numbers']['flow']);
        $this->assertSame(34, $facts['numbers']['head']);
        $station = $this->verified('DAB AQUAJET 82 M - G');
        $this->get('https://kotlov.by/nasosnyie-stantsii?pump[tank]=20')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$station->id]);
    }
}

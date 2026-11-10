<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\HeatPumpProductPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogSelectionNextTest extends TestCase
{
    use RefreshDatabase;

    public function test_heat_pump_hub_excludes_parts_and_the_rtc_page_shows_the_compatible_group(): void
    {
        $root = $this->category(HeatPumpProductPresenter::ROOT);
        DB::table('redirects')->where('from_url', '/'.$root->slug)->where('to_url', '/teplovye-nasosy')->update(['is_active' => false]);
        $air = $this->category(HeatPumpProductPresenter::AIR_WATER, $root->id);
        $parts = $this->category(HeatPumpProductPresenter::PARTS, $root->id);
        $pump = $this->product($air, 'regulus-rtc-13e', 'Тепловой насос Regulus RTC 13e');
        $group = $this->product($parts, 'nasosnaia-gruppa-s-reguliatorami-regulus-cse-ir-14', 'Насосная группа Regulus CSE IR 14');

        $rootResponse = $this->get('/'.$root->slug);
        $this->assertSame(200, $rootResponse->status(), 'Hub redirect: '.$rootResponse->headers->get('Location'));
        $rootResponse
            ->assertSeeText('SCOP 4,71')
            ->assertSeeText($pump->name)
            ->assertDontSeeText($group->name);

        $partsResponse = $this->get('/'.$parts->slug);
        $this->assertSame(200, $partsResponse->status(), 'Parts redirect: '.$partsResponse->headers->get('Location'));
        $partsResponse->assertSeeText($group->name);

        $pumpResponse = $this->get('https://kotlov.by/'.$air->slug.'/'.$pump->slug);
        $this->assertSame(200, $pumpResponse->status(), 'Pump redirect: '.$pumpResponse->headers->get('Location'));
        $pumpResponse
            ->assertSeeText('10,13 кВт (A7/W35, 79 Гц)')
            ->assertSeeText('Совместимые комплектующие')
            ->assertSeeText($group->name);
    }

    public function test_calculator_prefills_the_outlet_and_never_guesses_from_the_model_name(): void
    {
        $category = $this->category('topki');
        $verified = $this->product($category, 'topka-confirmed', 'Топка D120', [
            ['key' => 'Диаметр дымового патрубка (мм)', 'value' => '150', 'unit' => 'мм'],
        ]);
        $unknown = $this->product($category, 'topka-unknown', 'Топка D120');

        $this->get('/topki/'.$verified->slug)
            ->assertOk()
            ->assertSeeText('Подобрать дымоход для патрубка Ø 150 мм')
            ->assertSee(route('chimney-selector', ['equipment' => $verified->id]).'#calculator', false);
        $this->get(route('chimney-selector', ['equipment' => $verified->id]))
            ->assertOk()
            ->assertViewHas('defaultDiameter', '150');
        $this->get(route('chimney-selector', ['equipment' => $unknown->id]))
            ->assertOk()
            ->assertViewHas('defaultDiameter', fn ($diameter) => $diameter === null)
            ->assertDontSee('name="diameter" value="115" required checked', false);
    }

    public function test_gas_meter_passport_and_replacement_request_agree_with_the_catalog_filter(): void
    {
        $category = $this->category('schetchiki-gaza');
        $meter = $this->product($category, 'schetchik-gaza-bytovoj-rusbelgaz-rbg-u-g16-a', 'Счётчик Русбелгаз РБГ У G1,6 А');
        $other = $this->product($category, 'left-meter', 'Счётчик газа G4 (левый) L=200');
        (require database_path('migrations/2026_11_10_120000_add_gas_meter_connection_filters.php'))->up();

        $attribute = DB::table('attributes')->where('category_id', $category->id)->where('name', 'Расстояние между патрубками')->value('id');
        $option = DB::table('attribute_options')->where('attribute_id', $attribute)->where('name', 'До 177 мм')->value('id');
        $this->assertNotNull($option);

        $this->get('/'.$category->slug.'?'.http_build_query(['attr' => [$attribute => [$option]]]))
            ->assertOk()
            ->assertSeeText($meter->name)
            ->assertDontSeeText($other->name);
        $this->get('/'.$category->slug.'/'.$meter->slug)
            ->assertOk()
            ->assertSeeText('Не более 177 мм')
            ->assertSeeText('Подобрать замену по этой модели');
        $this->get(route('contacts', ['replace_meter' => $meter->slug]))
            ->assertOk()
            ->assertSeeText('Прошу подобрать замену газового счётчика Русбелгаз РБГ У G1,6 А.');
    }

    private function category(string $slug, int $parent = 0): Category
    {
        return Category::updateOrCreate(['slug' => $slug], [
            'name' => $slug, 'parent_id' => $parent, 'is_active' => true,
        ]);
    }

    private function product(Category $category, string $slug, string $name, array $specs = []): Product
    {
        return Product::create([
            'slug' => $slug, 'sku' => $slug, 'name' => $name, 'category_id' => $category->id,
            'price' => 100, 'in_stock' => true, 'is_active' => true, 'is_archived' => false, 'specs' => $specs,
        ]);
    }
}

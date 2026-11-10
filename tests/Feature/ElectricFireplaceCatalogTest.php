<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectricFireplaceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_uses_the_same_heating_facts_for_cards_filters_and_details(): void
    {
        $category = Category::query()->create(['name' => 'Электрокамины', 'slug' => 'elektrokamini', 'is_active' => true]);
        $add = fn (string $slug, string $name, array $specs) => Product::query()->create([
            'category_id' => $category->id, 'slug' => $slug, 'name' => $name, 'sku' => $slug, 'price' => 100,
            'is_active' => true, 'in_stock' => true, 'manual_stock_management' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK, 'specs' => $specs,
        ]);

        $heater = $add('ocag-elektriceskii-electrolux-sphere-plus-efpp-3320rls-n', 'Electrolux Sphere Plus EFP/P-3320RLS N', [
            ['key' => 'Площадь обогрева, м²', 'value' => '20'],
            ['key' => 'Площадь обогрева, м&#178;', 'value' => '25'],
            ['key' => 'Технология имитации пламени', 'value' => 'Real Fire Perfect'],
        ]);
        $steam = $add('ocag-parovoi-elektriceskii-royal-thermo-rtfpp1000m-3d-cassette-mystery', 'Очаг паровой Royal Thermo Mystery', [
            ['key' => 'Макс. потребляемая мощность', 'value' => '230 Вт'],
        ]);
        $unknown = $add('unverified-effect', 'Очаг без данных о нагревателе', [
            ['key' => 'Режим работы', 'value' => 'без обогрева'],
        ]);
        $bio = $add('bio-example', 'Тумба с биокамином', [['key' => 'Обогрев', 'value' => 'да']]);
        $supplier = Supplier::query()->create(['code' => 'electric-test', 'name' => 'Поставщик', 'is_active' => true]);
        SupplierProduct::query()->create([
            'product_id' => $heater->id, 'supplier_id' => $supplier->id,
            'supplier_article' => $heater->sku, 'match_confidence' => 'brand_assignment',
        ]);

        $url = 'https://kotlov.by/elektrokamini';
        $page = $this->get($url)->assertOk()
            ->assertSeeText('Электрокамины в Минске')->assertSeeText('33″')
            ->assertSeeText('до 25 м²')->assertSeeText('Водяной пар')->assertSeeText('Биокамин')
            ->assertDontSeeText('20 м²');
        $this->assertSame(6, substr_count($page->getContent(), 'class="fireplace-card-specs '));

        $this->get($url.'?electric_mode=heat')->assertOk()
            ->assertViewHas('products', fn ($page) => $page->pluck('id')->all() === [$heater->id]);
        $this->get($url.'?electric_mode=no-heat')->assertOk()
            ->assertViewHas('products', fn ($page) => $page->pluck('id')->all() === [$steam->id]);
        $this->get($url.'?electric_mode=steam&in_stock=1')->assertOk()
            ->assertViewHas('products', fn ($page) => $page->total() === 0);
        $this->get($url.'?in_stock=1')->assertOk()
            ->assertViewHas('products', fn ($page) => $page->pluck('id')->all() === [$heater->id]);

        $this->get($url.'/'.$heater->slug)->assertOk()
            ->assertViewHas('fireplaceSpecs', fn (array $specs) => $specs['Площадь обогрева'] === 'до 25 м²'
                && $specs['Технология имитации пламени'] === 'Real Fire Perfect');
    }
}

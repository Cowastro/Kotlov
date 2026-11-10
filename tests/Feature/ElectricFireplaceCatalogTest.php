<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
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
    public function test_opening_filter_uses_installation_width_and_clean_pages_have_their_own_canonical(): void
    {
        $category = Category::query()->create(['name' => 'Электрокамины', 'slug' => 'elektrokamini', 'is_active' => true]);
        $add = fn (string $slug, array $specs = []) => Product::query()->create([
            'category_id' => $category->id, 'slug' => $slug, 'name' => $slug, 'sku' => $slug, 'price' => 100,
            'is_active' => true, 'availability_status' => Product::AVAILABILITY_CHECK, 'specs' => $specs,
        ]);
        $compact = $add('compact-test-opening', [
            ['key' => 'Размеры ниши (В×Ш×Г), см', 'value' => '40 × 60 × 20,5'],
            ['key' => 'Ширина корпуса, мм', 'value' => '650'],
            ['key' => 'Мощность обогрева, кВт', 'value' => '1,5'],
        ]);
        $wide = $add('elektrokamin-dimplex-xhd28l-int');
        $add('ambiguous-test-opening', [['key' => 'Размеры ниши', 'value' => '40 × 60 × 20,5 см']]);
        $add('conflicting-test-opening', [
            ['key' => 'Ширина ниши, мм', 'value' => '570'],
            ['key' => 'Ширина встраивания, мм', 'value' => '650'],
        ]);
        for ($i = 1; $i <= 21; $i++) {
            $add('test-electric-fireplace-'.$i, [['key' => 'Ширина корпуса, мм', 'value' => '500']]);
        }

        $url = 'https://kotlov.by/elektrokamini';
        $canonical = '<link rel="canonical" href="'.$url.'">';
        $this->get($url.'?page=1')->assertOk()->assertSee($canonical, false);
        $this->get($url.'?page=2')->assertOk()->assertSee('<link rel="canonical" href="'.$url.'?page=2">', false);
        $this->get($url.'?page=3')->assertNotFound();
        $this->get($url.'?page=2&sort=price_asc')->assertOk()->assertSee($canonical, false);
        $this->get($url.'?niche_width_max[]=600')->assertOk()->assertViewHas('products', fn ($page) => $page->total() === 25);
        $this->get($url.'?electric_mode=heat&niche_width_max=600')->assertOk()
            ->assertSee($canonical, false)->assertSeeText('Ниша 600 × 400 × 205 мм')
            ->assertSeeText('Ниша до 600 мм')
            ->assertViewHas('products', fn ($page) => $page->pluck('id')->all() === [$compact->id]);

        $this->get($url.'/'.$compact->slug)->assertOk()
            ->assertViewHas('fireplaceSpecs', fn (array $rows) => $rows['Ширина ниши'] === '600 мм'
                && $rows['Глубина ниши'] === '205 мм');
        $this->actingAs(User::factory()->create());
        $this->postJson('https://kotlov.by/compare/add', ['product_id' => $compact->id])->assertOk();
        $this->postJson('https://kotlov.by/compare/add', ['product_id' => $wide->id])->assertOk();
        $this->get('https://kotlov.by/compare')->assertOk()
            ->assertViewHas('openingByProduct', fn ($rows) => ($rows[$compact->id]['Ширина ниши'] ?? null) === '600 мм'
                && ($rows[$wide->id]['Ширина ниши'] ?? null) === '695 мм')
            ->assertSeeText('Ширина ниши');
        $this->get('https://kotlov.by/robots.txt')->assertOk()
            ->assertSeeText('Allow: /elektrokamini?page=')
            ->assertSee('Disallow: /elektrokamini?page=*&', false);
    }
}

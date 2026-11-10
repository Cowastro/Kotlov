<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SeoSurfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.base_domain' => 'kotlov.by']);
        Cache::forget('sitemap.xml.v4');
    }

    public function test_sitemap_contains_only_primary_domain_urls(): void
    {
        Category::query()->create([
            'parent_id' => 0,
            'name' => 'Газовые котлы',
            'slug' => 'gazovye',
            'is_active' => true,
        ]);

        $response = $this->get('https://kotlov.by/sitemap.xml');

        $response->assertOk();
        $xml = $response->getContent();
        preg_match_all('#<loc>([^<]+)</loc>#', $xml, $matches);

        $this->assertNotEmpty($matches[1]);
        $this->assertContains('https://kotlov.by/gazovye', $matches[1]);
        foreach ($matches[1] as $location) {
            $this->assertSame('kotlov.by', parse_url($location, PHP_URL_HOST));
        }
    }

    public function test_active_city_sitemap_redirects_to_primary_sitemap(): void
    {
        $this->enableCityStorefront('gomel');

        $this->get('https://gomel.kotlov.by/sitemap.xml')
            ->assertStatus(301)
            ->assertRedirect('https://kotlov.by/sitemap.xml');
    }

    public function test_active_city_robots_redirects_to_primary_robots(): void
    {
        $this->enableCityStorefront('gomel');

        $this->get('https://gomel.kotlov.by/robots.txt')
            ->assertStatus(301)
            ->assertRedirect('https://kotlov.by/robots.txt');
    }

    public function test_fireplace_catalog_exposes_only_unfiltered_pagination_to_search_engines(): void
    {
        $category = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Печи-камины',
            'slug' => 'pechi-kaminy',
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 25; $i++) {
            Product::query()->create([
                'category_id' => $category->id,
                'name' => "Печь-камин $i",
                'slug' => "test-fireplace-$i",
                'sku' => "FIREPLACE-$i",
                'price' => 100,
                'is_active' => true,
                'availability_status' => Product::AVAILABILITY_CHECK,
            ]);
        }

        $url = 'https://kotlov.by/pechi-kaminy';
        $canonical = '<link rel="canonical" href="'.$url.'">';
        $this->get($url)->assertOk()->assertSee($canonical, false)->assertSee($url.'?page=2', false);
        $this->get($url.'?page=1')->assertOk()->assertSee($canonical, false);
        $this->get($url.'?page=2')->assertOk()->assertSee('<link rel="canonical" href="'.$url.'?page=2">', false);
        $this->get($url.'?page=2&sort=price_asc')->assertOk()->assertSee($canonical, false);
        $this->get($url.'?page=3')->assertNotFound();

        $this->get('https://kotlov.by/robots.txt')
            ->assertOk()
            ->assertSeeText('Allow: /pechi-kaminy?page=')
            ->assertSee('Disallow: /pechi-kaminy?page=*&', false);
    }

    public function test_minsk_fireplaces_diversify_the_first_row_and_filter_by_confirmed_stock(): void
    {
        $category = Category::query()->create(['parent_id' => 0, 'name' => 'Печи-камины', 'slug' => 'pechi-kaminy', 'is_active' => true]);
        $supplier = Supplier::query()->create(['code' => 'fireplace-test-supplier', 'name' => 'Поставщик', 'is_active' => true]);
        $brands = collect(range(1, 4))->map(fn (int $i) => Brand::query()->create(['name' => "Бренд $i", 'slug' => "fireplace-brand-$i", 'is_active' => true]));

        $ids = collect([0, 0, 1, 2, 3])->map(function (int $brand, int $i) use ($brands, $category, $supplier): int {
            $confirmed = $i < 4;
            $product = Product::query()->create([
                'category_id' => $category->id,
                'brand_id' => $brands[$brand]->id,
                'name' => "Печь-камин $i",
                'slug' => "fireplace-row-$i",
                'sku' => "FIREPLACE-ROW-$i",
                'price' => 100 + $i,
                'popularity_score' => 10 - $i,
                'is_active' => true,
                'in_stock' => true,
                'manual_stock_management' => true,
                'availability_status' => $confirmed ? Product::AVAILABILITY_IN_STOCK : Product::AVAILABILITY_CHECK,
            ]);
            SupplierProduct::query()->create([
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
                'supplier_article' => $product->sku,
                'match_confidence' => 'brand_assignment',
            ]);

            return $product->id;
        })->all();

        $url = 'https://kotlov.by/pechi-kaminy';
        $this->get($url)->assertOk()
            ->assertSeeText('Печи-камины для дома и дачи в Минске')
            ->assertSee($url.'?in_stock=1#catalog-products', false)
            ->assertSeeText('Офис KOTLOV в Минске')
            ->assertSeeText('Самовывоз со склада KOTLOV в Минске')
            ->assertViewHas('products', fn ($page) => $page->pluck('id')->all() === [$ids[0], $ids[2], $ids[3], $ids[4], $ids[1]]);
        $this->get($url.'?in_stock=1')->assertOk()
            ->assertViewHas('products', fn ($page) => $page->total() === 4
                && $page->pluck('id')->all() === [$ids[0], $ids[2], $ids[3], $ids[1]]);
        $this->get($url.'?sort=price_asc')->assertOk()
            ->assertViewHas('products', fn ($page) => $page->pluck('id')->all() === $ids);
    }

    public function test_fireplace_cards_and_details_share_the_same_specs(): void
    {
        $category = Category::query()->create(['parent_id' => 0, 'name' => 'Печи-камины', 'slug' => 'pechi-kaminy', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Печь-камин тестовая',
            'slug' => 'test-fireplace-specs',
            'sku' => 'FIREPLACE-SPECS',
            'price' => 100,
            'is_active' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'specs' => [
                ['key' => 'Тепловая мощность, кВт', 'value' => '6 кВт', 'unit' => 'кВт'],
                ['key' => 'Площадь обогрева, м2', 'value' => 'от 25 до 55 м2'],
                ['key' => 'Объём отапливаемого помещения', 'value' => '135 м³'],
                ['key' => 'Диаметр дымохода, мм', 'value' => '120', 'unit' => 'мм'],
                ['key' => 'Диаметр дымоходного патрубка', 'value' => '120 мм'],
                ['key' => 'КПД, %', 'value' => '80 %', 'unit' => '%'],
            ],
        ]);
        $area = Attribute::query()->create([
            'category_id' => $category->id, 'name' => 'Площадь отапливаемого помещения', 'type' => 'select', 'in_product' => true,
        ]);
        $material = Attribute::query()->create([
            'category_id' => $category->id, 'name' => 'Материал', 'type' => 'select', 'in_product' => true,
        ]);
        $product->allAttributeValues()->createMany([
            ['attribute_id' => $area->id, 'option_id' => AttributeOption::query()->create(['attribute_id' => $area->id, 'name' => '50–100 м²'])->id],
            ['attribute_id' => $material->id, 'option_id' => AttributeOption::query()->create(['attribute_id' => $material->id, 'name' => 'Сталь'])->id],
        ]);

        $response = $this->get('https://kotlov.by/pechi-kaminy')->assertOk()
            ->assertSeeText('6 кВт')
            ->assertSeeText('25–55 м²')
            ->assertSeeText('Сталь')
            ->assertDontSeeText('135 м³');
        $this->assertSame(2, substr_count($response->getContent(), 'class="fireplace-card-specs '));

        $this->get('https://kotlov.by/pechi-kaminy/'.$product->slug)->assertOk()
            ->assertViewHas('fireplaceSpecs', [
                'Мощность' => '6 кВт',
                'Площадь обогрева' => '25–55 м²',
                'Материал' => 'Сталь',
                'Объём отапливаемого помещения' => '135 м³',
                'Диаметр дымохода' => '120 мм',
                'КПД' => '80 %',
            ])
            ->assertSeeText('Самовывоз со склада KOTLOV: '.config('shop.delivery_methods.pickup.address'))
            ->assertDontSeeText('Самовывоз: ул. Селицкого 39Б');
    }

    private function enableCityStorefront(string $slug): City
    {
        return City::query()->create([
            'slug' => $slug,
            'name' => 'Гомель',
            'name_in' => 'в Гомеле',
            'name_title' => 'Гомеле',
            'is_active' => true,
            'storefront_enabled' => true,
        ]);
    }
}

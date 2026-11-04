<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\Category;
use App\Models\City;
use App\Models\PartnerBranch;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityStorefrontSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.base_domain' => 'kotlov.by']);
        $this->city('minsk', 'Минск');
    }

    public function test_approved_branch_gets_a_local_storefront_with_map_and_schema(): void
    {
        $borisov = $this->city('borisov', 'Борисов');
        $category = $this->categoryWithProduct('pechki', 'Печи');
        $this->branch($borisov, [
            'storefront_title' => 'Магазин печей KOTLOV в Борисове',
            'storefront_intro' => 'Подбор печей и дымоходов в Борисове.',
            'storefront_promo' => 'Бесплатная проверка комплектации дымохода.',
            'map_url' => 'https://maps.example/borisov',
            'seo_title' => 'Котлы и печи в Борисове | KOTLOV',
        ]);

        $this->get('https://borisov.kotlov.by/')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://borisov.kotlov.by/">', false)
            ->assertSee('<title>Котлы и печи в Борисове | KOTLOV</title>', false)
            ->assertSee('Магазин печей KOTLOV в Борисове')
            ->assertSee('Подбор печей и дымоходов в Борисове.')
            ->assertSee('Бесплатная проверка комплектации дымохода.')
            ->assertSee('https://maps.example/borisov')
            ->assertSee('/'.$category->slug)
            ->assertSee('"@type":"Store"', false)
            ->assertSee('"addressLocality":"Борисов"', false);
    }

    public function test_every_active_category_is_a_city_landing_but_product_stays_canonical(): void
    {
        $borisov = $this->city('borisov', 'Борисов');
        $category = $this->categoryWithProduct('nasosy', 'Печи', 'Печи купить в Минске');
        $category->update(['content' => '<p>Большой выбор печей в Минске.</p>']);
        $this->branch($borisov);

        $this->get('https://borisov.kotlov.by/'.$category->slug)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://borisov.kotlov.by/nasosy">', false)
            ->assertSee('Печи в Борисове')
            ->assertSee('Печи купить в Борисове', false)
            ->assertSee('Большой выбор печей в Борисове.', false)
            ->assertSee('Локальная витрина KOTLOV')
            ->assertDontSee('Печи купить в Минске', false);

        $this->get('https://borisov.kotlov.by/nasosy/test-product')
            ->assertRedirect('https://kotlov.by/nasosy/test-product');
    }

    public function test_city_sitemap_contains_landings_not_duplicate_products(): void
    {
        $borisov = $this->city('borisov', 'Борисов');
        $this->categoryWithProduct('pechki', 'Печи');
        $this->branch($borisov);

        $this->get('https://borisov.kotlov.by/sitemap.xml')
            ->assertOk()
            ->assertSee('https://borisov.kotlov.by/', false)
            ->assertSee('https://borisov.kotlov.by/catalog', false)
            ->assertSee('https://borisov.kotlov.by/pechki', false)
            ->assertDontSee('test-product', false);

        $this->get('https://borisov.kotlov.by/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: https://borisov.kotlov.by/sitemap.xml');
    }

    public function test_city_catalog_index_stays_local_and_uses_city_metadata(): void
    {
        $borisov = $this->city('borisov', 'Борисов');
        $this->categoryWithProduct('pechki', 'Печи');
        $this->branch($borisov);

        $this->get('https://borisov.kotlov.by/catalog')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://borisov.kotlov.by/catalog">', false)
            ->assertSee('Каталог товаров в Борисове')
            ->assertSee('Каталог отопительного оборудования в Борисове | KOTLOV', false);
    }

    public function test_city_without_an_approved_branch_does_not_create_storefront_pages(): void
    {
        $borisov = $this->city('borisov', 'Борисов');
        $category = $this->categoryWithProduct('pechki', 'Печи');
        $this->branch($borisov, [], 'pending');

        $this->get('https://borisov.kotlov.by/'.$category->slug)
            ->assertRedirect('https://kotlov.by/'.$category->slug);
    }

    private function city(string $slug, string $name): City
    {
        return City::query()->create([
            'slug' => $slug,
            'name' => $name,
            'name_in' => match ($name) {
                'Минск' => 'в Минске',
                'Борисов' => 'в Борисове',
                default => 'в '.$name,
            },
            'name_title' => $name === 'Борисов' ? 'Борисове' : $name,
            'region' => $name,
            'is_active' => true,
            'storefront_enabled' => true,
        ]);
    }

    private function branch(City $city, array $extra = [], string $status = 'approved'): PartnerBranch
    {
        $user = User::factory()->create(['is_active' => true]);
        $client = B2bClient::query()->create([
            'user_id' => $user->id,
            'status' => $status,
            'partner_type' => 'wholesale',
            'company_name' => 'ООО «СБГ бай»',
            'contact_email' => $user->email,
        ]);

        return PartnerBranch::query()->create(array_merge([
            'city_id' => $city->id,
            'b2b_client_id' => $client->id,
            'name' => 'Магазин «Печкiн»',
            'address' => 'г. Борисов, ул. Дёмина, 39',
            'phones' => ['+375 (29) 111-22-33'],
            'working_hours' => 'Пн–Пт 9:00–18:00',
            'is_active' => true,
        ], $extra));
    }

    private function categoryWithProduct(string $slug, string $name, ?string $metaTitle = null): Category
    {
        $category = Category::query()->create([
            'parent_id' => 0,
            'name' => $name,
            'h1' => $name,
            'slug' => $slug,
            'meta_title' => $metaTitle,
            'is_active' => true,
        ]);

        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Тестовый товар',
            'slug' => 'test-product',
            'sku' => 'CITY-TEST',
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);

        return $category;
    }
}

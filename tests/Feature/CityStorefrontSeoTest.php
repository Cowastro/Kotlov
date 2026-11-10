<?php

namespace Tests\Feature;

use App\Http\Middleware\CitySubdomain;
use App\Models\B2bClient;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\PartnerBranch;
use App\Models\Product;
use App\Models\User;
use App\Services\CityContactResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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
        // A city without its own branded hero shows the branch title and intro in the first slide.
        $molodechno = $this->city('molodechno', 'Молодечно');
        $category = $this->categoryWithProduct('pechki', 'Печи');
        $this->branch($molodechno, [
            'storefront_title' => 'Магазин печей KOTLOV в Молодечно',
            'storefront_intro' => 'Подбор печей и дымоходов в Молодечно.',
            'storefront_promo' => 'Бесплатная проверка комплектации дымохода.',
            'map_url' => 'https://maps.example/molodechno',
            'seo_title' => 'Котлы и печи в Молодечно | KOTLOV',
        ]);

        $this->get('https://molodechno.kotlov.by/')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://molodechno.kotlov.by/">', false)
            ->assertSee('<title>Котлы и печи в Молодечно | KOTLOV</title>', false)
            ->assertSee('Магазин печей KOTLOV в Молодечно')
            ->assertSee('Подбор печей и дымоходов в Молодечно.')
            ->assertSee('Популярные разделы')
            ->assertSee('home-popular-categories', false)
            ->assertSee('Бесплатная проверка комплектации дымохода.')
            ->assertSee('https://maps.example/molodechno')
            ->assertSee('https://www.google.com/maps?q=', false)
            ->assertSee('/'.$category->slug)
            ->assertSee('"@type":"Store"', false)
            ->assertSee('"addressLocality":"Молодечно"', false);
    }

    public function test_borisov_and_lida_have_the_branded_city_hero(): void
    {
        $borisov = $this->city('borisov', 'Борисов');
        $this->categoryWithProduct('pechki', 'Печи');
        $this->branch($borisov, [
            'storefront_title' => 'Магазин печей KOTLOV в Борисове',
            'storefront_promo' => 'Бесплатная проверка комплектации дымохода.',
            'seo_title' => 'Котлы и печи в Борисове | KOTLOV',
        ]);

        // The branded slide carries the city wordmark instead of the branch title; the promo stays in the branch block.
        $this->get('https://borisov.kotlov.by/')
            ->assertOk()
            ->assertSee('<title>Котлы и печи в Борисове | KOTLOV</title>', false)
            ->assertSee('city-home-slide--branded', false)
            ->assertSee('BORISOV.KOTLOV.BY')
            ->assertSee('Магазин отопления')
            ->assertDontSee('Магазин печей KOTLOV в Борисове')
            ->assertSee('Бесплатная проверка комплектации дымохода.')
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
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://kotlov.by/nasosy/test-product">', false)
            ->assertSee('г. Борисов');
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

    public function test_city_host_uses_its_branch_contact_even_with_another_city_cookie(): void
    {
        $borisov = $this->city('borisov', 'Борисов');
        $this->branch($borisov, [
            'phones' => ['+375 (29) 111-22-33'],
            'email' => 'borisov@example.test',
        ]);

        $contact = app(CityContactResolver::class)->contactForCity($borisov);
        $this->assertSame(['+375 (29) 111-22-33'], $contact['phones']);
        $this->assertSame('borisov@example.test', $contact['email']);

        $request = Request::create(
            'https://borisov.kotlov.by/',
            'GET',
            cookies: ['selected_city' => 'minsk'],
        );
        $response = app(CitySubdomain::class)->handle(
            $request,
            static fn () => response()->json([
                'city' => view()->shared('selectedCity')?->slug,
                'phone' => view()->shared('cityContact')['phones'][0] ?? null,
            ]),
        );

        $this->assertSame(
            [
                'city' => 'borisov',
                'phone' => '+375 (29) 111-22-33',
            ],
            $response->getData(true),
        );
    }

    public function test_city_selector_opens_the_connected_storefront_and_can_return_to_minsk(): void
    {
        $this->branch($this->city('borisov', 'Борисов'));

        $this->get('https://kotlov.by/city/select/borisov?return=%2Fcatalog')
            ->assertRedirect('https://borisov.kotlov.by/')
            ->assertPlainCookie('selected_city', 'borisov');

        $this->get('https://borisov.kotlov.by/city/select/minsk?return=%2Fcatalog')
            ->assertRedirect('https://kotlov.by/city/select/minsk?return=%2Fcatalog');

        // The city form of a storefront posts to «/city/select»: the way back to Minsk keeps the city (it was a 404).
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('https://borisov.kotlov.by/city/select', ['slug' => 'minsk', 'return' => '/catalog'])
            ->assertRedirect('https://kotlov.by/city/select/minsk?return=%2Fcatalog');

        $this->get('https://kotlov.by/city/select/minsk?return=%2Fcatalog')
            ->assertRedirect('https://kotlov.by/catalog')
            ->assertPlainCookie('selected_city', 'minsk');
    }

    public function test_lida_selection_and_catalog_navigation_stay_on_the_partner_host(): void
    {
        $this->branch($this->city('borisov', 'Борисов'));
        $lida = $this->city('lida', 'Лида');
        $lida->update(['name_in' => 'в Лиде', 'name_title' => 'Лиде']);
        $this->branch($lida, ['name' => 'Магазин KOTLOV в Лиде', 'address' => 'г. Лида, ул. Калинина, 57', 'map_embed_url' => 'https://www.google.com/maps?q=53.889934,25.316627&output=embed']);
        $this->categoryWithProduct('pechki', 'Печи');

        $this->get('https://lida.kotlov.by/')
            ->assertOk()
            ->assertSee('https://www.google.com/maps?q=53.889934,25.316627&amp;output=embed', false);

        foreach (['kotlov.by', 'borisov.kotlov.by', 'lida.kotlov.by'] as $host) {
            $this->get('https://'.$host.'/city/select/lida?return=%2Fcatalog')
                ->assertRedirect('https://lida.kotlov.by/');
        }

        $this->get('https://lida.kotlov.by/pechki/test-product')
            ->assertOk()
            ->assertSee('г. Лида')
            ->assertSee('<link rel="canonical" href="https://kotlov.by/pechki/test-product">', false);

        Brand::query()->create(['name' => 'Test Brand', 'slug' => 'test-brand', 'is_active' => true]);
        $this->get('https://lida.kotlov.by/brands/test-brand')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://kotlov.by/brands/test-brand">', false);

        $this->get('https://lida.kotlov.by/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: https://lida.kotlov.by/sitemap.xml')
            ->assertDontSee('Sitemap: https://borisov.kotlov.by/sitemap.xml');
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

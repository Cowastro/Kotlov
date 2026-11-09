<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Http\Middleware\HandleRedirects;
use App\Providers\AppServiceProvider;
use Database\Seeders\CitiesSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacySeoRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.base_domain' => 'kotlov.by']);
    }

    public function test_nested_legacy_category_redirects_to_current_flat_url(): void
    {
        $this->enableCityStorefront('krugloe');

        Category::query()->firstOrCreate(['slug' => 'tsentrobejnye'], [
            'parent_id' => 0,
            'name' => 'Центробежные насосы',
            'is_active' => true,
        ]);

        $response = $this->get('https://krugloe.kotlov.by/nasosy/poverhnostnyie/tsentrobejnye');

        $response->assertStatus(301);
        $response->assertRedirect('https://kotlov.by/tsentrobejnye');
    }

    public function test_old_nested_pellet_boiler_category_redirects_to_current_category(): void
    {
        $this->enableCityStorefront('vitebsk');

        Category::query()->firstOrCreate(['slug' => 'kotly-na-pelletah'], [
            'parent_id' => 0,
            'name' => 'Пеллетные котлы',
            'is_active' => true,
        ]);

        foreach ([
            '/tverdotoplivnye/kotly-na-pelletah',
            '/kotly/tverdotoplivnye/kotly-na-pelletah',
        ] as $path) {
            $response = $this->get('https://vitebsk.kotlov.by' . $path);

            $response->assertStatus(301);
            $response->assertRedirect('https://kotlov.by/kotly-na-pelletah');
        }
    }

    public function test_retired_dacha_aggregate_redirects_to_current_stove_root(): void
    {
        $response = $this->get('https://kotlov.by/dlya-dachi?brand=example');

        $response->assertStatus(301);
        $response->assertRedirect('https://kotlov.by/pechki?brand=example');
    }

    public function test_default_social_image_points_to_an_existing_catalog_asset(): void
    {
        $response = $this->withViewErrors([])->get('https://kotlov.by/about');
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('https://kotlov.by/img/popular/catalog.jpg', $html);
        $this->assertStringNotContainsString('/img/og-default.jpg', $html);
    }

    public function test_removed_legacy_product_falls_back_to_nearest_active_category(): void
    {
        $this->enableCityStorefront('skidel');

        Category::query()->firstOrCreate(['slug' => 'pogrujnye'], [
            'parent_id' => 0,
            'name' => 'Погружные насосы',
            'is_active' => true,
        ]);

        $response = $this->get('https://skidel.kotlov.by/nasosy/pogrujnye/removed-product');

        $response->assertStatus(301);
        $response->assertRedirect('https://kotlov.by/pogrujnye');
    }

    public function test_canonical_product_path_is_not_collapsed_to_its_category(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'gazovye'], [
            'parent_id' => 0,
            'name' => 'Газовые котлы',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Газовый котёл тест',
            'slug' => 'gazovyj-kotel-test',
            'is_active' => true,
            'is_archived' => false,
        ]);

        $request = Request::create('/gazovye/gazovyj-kotel-test', 'GET');
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(204, $response->getStatusCode());
        $this->assertNull($response->headers->get('Location'));
    }

    public function test_city_product_page_redirects_to_primary_domain_canonical(): void
    {
        $this->enableCityStorefront('gomel');

        $category = Category::query()->firstOrCreate(['slug' => 'gazovye'], [
            'parent_id' => 0,
            'name' => 'Газовые котлы',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Газовый котёл тест',
            'slug' => 'gazovyj-kotel-test',
            'is_active' => true,
            'is_archived' => false,
        ]);

        $request = Request::create(
            'https://gomel.kotlov.by/gazovye/gazovyj-kotel-test?utm_source=test',
            'GET',
        );
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(
            'https://kotlov.by/gazovye/gazovyj-kotel-test?utm_source=test',
            $response->headers->get('Location'),
        );
    }

    public function test_active_city_category_redirects_to_primary_catalog(): void
    {
        Category::query()->firstOrCreate(['slug' => 'gazovye'], [
            'parent_id' => 0,
            'name' => 'Газовые котлы',
            'is_active' => true,
        ]);

        City::query()->firstOrCreate(['slug' => 'gomel'], [
            'name' => 'Гомель',
            'name_in' => 'в Гомеле',
            'name_title' => 'Гомеле',
            'is_active' => true,
            'storefront_enabled' => true,
        ]);

        $request = Request::create('https://gomel.kotlov.by/gazovye', 'GET');
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(
            'https://kotlov.by/gazovye',
            $response->headers->get('Location'),
        );
    }

    public function test_active_city_root_can_remain_a_local_presence_page(): void
    {
        $this->enableCityStorefront('gomel');

        $request = Request::create('https://gomel.kotlov.by/', 'GET');
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(204, $response->getStatusCode());
        $this->assertNull($response->headers->get('Location'));
    }

    public function test_active_city_brand_page_redirects_to_primary_catalog(): void
    {
        $this->enableCityStorefront('gomel');
        Brand::query()->create([
            'name' => 'Тестовый бренд',
            'slug' => 'test-brand',
            'is_active' => true,
        ]);

        $request = Request::create('https://gomel.kotlov.by/brands/test-brand', 'GET');
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(
            'https://kotlov.by/brands/test-brand',
            $response->headers->get('Location'),
        );
    }

    public function test_inactive_known_city_redirects_to_primary_domain(): void
    {
        Category::query()->firstOrCreate(['slug' => 'gazovye'], [
            'parent_id' => 0,
            'name' => 'Газовые котлы',
            'is_active' => true,
        ]);
        City::query()->create([
            'slug' => 'gomel',
            'name' => 'Гомель',
            'name_in' => 'в Гомеле',
            'name_title' => 'Гомеле',
            'is_active' => false,
        ]);

        $request = Request::create(
            'https://gomel.kotlov.by/gazovye?utm_source=google',
            'GET',
        );
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(
            'https://kotlov.by/gazovye?utm_source=google',
            $response->headers->get('Location'),
        );
    }

    public function test_city_without_enabled_storefront_redirects_to_primary_domain(): void
    {
        Category::query()->firstOrCreate(['slug' => 'radiatory'], [
            'parent_id' => 0,
            'name' => 'Радиаторы',
            'is_active' => true,
        ]);
        City::query()->create([
            'slug' => 'grodno',
            'name' => 'Гродно',
            'name_in' => 'в Гродно',
            'name_title' => 'Гродно',
            'is_active' => true,
            'storefront_enabled' => false,
        ]);

        $request = Request::create('https://grodno.kotlov.by/radiatory', 'GET');
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(
            'https://kotlov.by/radiatory',
            $response->headers->get('Location'),
        );
    }

    public function test_inactive_city_alias_redirects_directly_to_primary_domain(): void
    {
        Category::query()->firstOrCreate(['slug' => 'gazovye'], [
            'parent_id' => 0,
            'name' => 'Газовые котлы',
            'is_active' => true,
        ]);
        City::query()->create([
            'slug' => 'rechica',
            'name' => 'Речица',
            'name_in' => 'в Речице',
            'name_title' => 'Речице',
            'is_active' => true,
            'storefront_enabled' => false,
        ]);

        $request = Request::create('http://rechitsa.kotlov.by/gazovye', 'GET');
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(
            'https://kotlov.by/gazovye',
            $response->headers->get('Location'),
        );
    }

    public function test_inactive_city_static_page_redirects_to_primary_domain(): void
    {
        City::query()->create([
            'slug' => 'grodno',
            'name' => 'Гродно',
            'name_in' => 'в Гродно',
            'name_title' => 'Гродно',
            'is_active' => true,
            'storefront_enabled' => false,
        ]);

        $request = Request::create('https://grodno.kotlov.by/contacts', 'GET');
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(
            'https://kotlov.by/contacts',
            $response->headers->get('Location'),
        );
    }

    public function test_inactive_city_product_redirects_to_primary_canonical_path(): void
    {
        $category = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Газовые котлы',
            'slug' => 'gazovye',
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Тестовый котёл',
            'slug' => 'testovyj-kotel',
            'sku' => 'SEO-CITY-1',
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
        ]);
        City::query()->create([
            'slug' => 'grodno',
            'name' => 'Гродно',
            'name_in' => 'в Гродно',
            'name_title' => 'Гродно',
            'is_active' => true,
            'storefront_enabled' => false,
        ]);

        $request = Request::create(
            'https://grodno.kotlov.by/incorrect-section/testovyj-kotel?ref=old',
            'GET',
        );
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(
            'https://kotlov.by/gazovye/testovyj-kotel?ref=old',
            $response->headers->get('Location'),
        );
    }

    public function test_inactive_city_unknown_url_returns_gone(): void
    {
        City::query()->create([
            'slug' => 'grodno',
            'name' => 'Гродно',
            'name_in' => 'в Гродно',
            'name_title' => 'Гродно',
            'is_active' => true,
            'storefront_enabled' => false,
        ]);

        $request = Request::create(
            'https://grodno.kotlov.by/musornyj-url-kotorogo-net',
            'GET',
        );
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(410, $response->getStatusCode());
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertNull($response->headers->get('Location'));
    }

    public function test_unknown_city_unknown_product_url_returns_gone(): void
    {
        $request = Request::create(
            'https://nesushhestvuyushhij-gorod.kotlov.by/fake-category/fake-product',
            'GET',
        );
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(410, $response->getStatusCode());
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertNull($response->headers->get('Location'));
    }

    public function test_inactive_city_gone_response_is_applied_in_full_http_pipeline(): void
    {
        City::query()->create([
            'slug' => 'grodno',
            'name' => 'Гродно',
            'name_in' => 'в Гродно',
            'name_title' => 'Гродно',
            'is_active' => true,
            'storefront_enabled' => false,
        ]);

        $this->get('https://grodno.kotlov.by/ne-sushhestvuet/voobsche')
            ->assertStatus(410)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeaderMissing('Location');
    }

    public function test_inactive_city_legacy_redirect_is_absolute_and_single_hop(): void
    {
        City::query()->create([
            'slug' => 'grodno',
            'name' => 'Гродно',
            'name_in' => 'в Гродно',
            'name_title' => 'Гродно',
            'is_active' => true,
            'storefront_enabled' => false,
        ]);

        $this->get('https://grodno.kotlov.by/kotly/tverdotoplivnye/kotly-na-pelletah')
            ->assertStatus(301)
            ->assertRedirect('https://kotlov.by/kotly-na-pelletah');
    }

    public function test_primary_domain_unknown_url_is_not_converted_to_gone_by_city_logic(): void
    {
        $request = Request::create('https://kotlov.by/ne-sushhestvuet', 'GET');
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(204, $response->getStatusCode());
    }

    public function test_active_city_alias_catalog_url_redirects_to_primary_domain(): void
    {
        $this->enableCityStorefront('rechica');
        Category::query()->create([
            'parent_id' => 0,
            'name' => 'Газовые котлы',
            'slug' => 'gazovye',
            'is_active' => true,
        ]);

        $request = Request::create('http://rechitsa.kotlov.by/gazovye', 'GET');
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(
            'https://kotlov.by/gazovye',
            $response->headers->get('Location'),
        );
    }

    public function test_active_city_alias_root_redirects_to_its_enabled_local_presence(): void
    {
        $this->enableCityStorefront('rechica');

        $request = Request::create('http://rechitsa.kotlov.by/', 'GET');
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(
            'https://rechica.kotlov.by/',
            $response->headers->get('Location'),
        );
    }

    public function test_city_navigation_only_exposes_active_storefronts(): void
    {
        City::query()->create([
            'slug' => 'gomel',
            'name' => 'Гомель',
            'name_in' => 'в Гомеле',
            'name_title' => 'Гомеле',
            'is_active' => true,
            'storefront_enabled' => true,
        ]);
        City::query()->create([
            'slug' => 'grodno',
            'name' => 'Гродно',
            'name_in' => 'в Гродно',
            'name_title' => 'Гродно',
            'is_active' => false,
            'storefront_enabled' => false,
        ]);

        $method = new \ReflectionMethod(AppServiceProvider::class, 'getCitiesByRegion');
        $cities = $method->invoke(new AppServiceProvider(app()));
        $slugs = collect($cities)->flatten(1)->pluck('slug');

        $this->assertTrue($slugs->contains('minsk'));
        $this->assertTrue($slugs->contains('gomel'));
        $this->assertFalse($slugs->contains('grodno'));
    }

    public function test_city_seeder_keeps_an_explicitly_enabled_storefront(): void
    {
        $this->enableCityStorefront('gomel');

        $this->seed(CitiesSeeder::class);

        $this->assertTrue(
            City::query()->where('slug', 'gomel')->value('storefront_enabled'),
        );
        $this->assertFalse(
            City::query()->where('slug', 'grodno')->value('storefront_enabled'),
        );
    }

    public function test_gas_meter_category_on_city_host_redirects_to_primary_domain(): void
    {
        Category::query()->firstOrCreate(['slug' => 'schetchiki-gaza'], [
            'parent_id' => 0,
            'name' => 'Счётчики газа',
            'is_active' => true,
        ]);
        City::query()->firstOrCreate(['slug' => 'svetlogorsk'], [
            'name' => 'Светлогорск',
            'name_in' => 'в Светлогорске',
            'name_title' => 'Светлогорске',
            'is_active' => true,
            'storefront_enabled' => true,
        ]);

        $request = Request::create(
            'https://svetlogorsk.kotlov.by/schetchiki-gaza?utm_source=google',
            'GET',
        );
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(
            'https://kotlov.by/schetchiki-gaza?utm_source=google',
            $response->headers->get('Location'),
        );
    }

    public function test_exact_product_redirect_wins_over_broad_legacy_rules(): void
    {
        $this->enableCityStorefront('gomel');

        $category = Category::query()->firstOrCreate(['slug' => 'teplovyie-nasosyi'], [
            'parent_id' => 0,
            'name' => 'Тепловые насосы',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Тепловой насос KOTLOV GE',
            'slug' => 'kotlov-ge-flm30-r32-10-kvt',
            'is_active' => true,
            'is_archived' => false,
        ]);

        DB::table('redirects')->insert([
            'from_url' => '/teplovyie-nasosyi/teplovoy-nasos-vozduh-voda-b3sd',
            'to_url' => '/teplovyie-nasosyi/kotlov-ge-flm30-r32-10-kvt',
            'status_code' => 301,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get('https://gomel.kotlov.by/teplovyie-nasosyi/teplovoy-nasos-vozduh-voda-b3sd');

        $response->assertStatus(301);
        $response->assertRedirect('https://kotlov.by/teplovyie-nasosyi/kotlov-ge-flm30-r32-10-kvt');
    }

    public function test_exact_redirect_replaces_an_archived_duplicate_product_url(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'teplovyie-nasosyi'], [
            'parent_id' => 0,
            'name' => 'Тепловые насосы',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Архивный тепловой насос',
            'slug' => 'old-duplicate-heat-pump',
            'is_active' => false,
            'is_archived' => true,
        ]);

        DB::table('redirects')->insert([
            'from_url' => '/teplovyie-nasosyi/old-duplicate-heat-pump',
            'to_url' => '/teplovyie-nasosyi/canonical-heat-pump',
            'status_code' => 301,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get('https://kotlov.by/teplovyie-nasosyi/old-duplicate-heat-pump');

        $response->assertStatus(301);
        $response->assertRedirect('https://kotlov.by/teplovyie-nasosyi/canonical-heat-pump');
    }

    public function test_old_parts_prefix_is_removed_instead_of_rewritten_to_wrong_section(): void
    {
        $this->enableCityStorefront('chechersk');
        Category::query()->create([
            'parent_id' => 0,
            'name' => 'Коллекторы отопления',
            'slug' => 'grebenki',
            'is_active' => true,
        ]);

        $response = $this->get('https://chechersk.kotlov.by/otoplenie-parts/grebenki');

        $response->assertStatus(301);
        $response->assertRedirect('https://kotlov.by/grebenki');
    }

    public function test_legacy_discounts_category_redirects_to_promotions_page(): void
    {
        $response = $this->get('https://kotlov.by/aktsiiiskidki');

        $response->assertStatus(301);
        $response->assertRedirect('https://kotlov.by/akcii');
    }

    private function enableCityStorefront(string $slug): City
    {
        return City::query()->create([
            'slug' => $slug,
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'name_in' => 'в городе',
            'name_title' => 'Городе',
            'is_active' => true,
            'storefront_enabled' => true,
        ]);
    }
}

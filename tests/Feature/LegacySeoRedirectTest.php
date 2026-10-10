<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\Product;
use App\Http\Middleware\HandleRedirects;
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
        Category::query()->firstOrCreate(['slug' => 'tsentrobejnye'], [
            'parent_id' => 0,
            'name' => 'Центробежные насосы',
            'is_active' => true,
        ]);

        $response = $this->get('https://krugloe.kotlov.by/nasosy/poverhnostnyie/tsentrobejnye');

        $response->assertStatus(301);
        $response->assertRedirect('https://krugloe.kotlov.by/tsentrobejnye');
    }

    public function test_old_nested_pellet_boiler_category_redirects_to_current_category(): void
    {
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
            $response->assertRedirect('https://vitebsk.kotlov.by/kotly-na-pelletah');
        }
    }

    public function test_removed_legacy_product_falls_back_to_nearest_active_category(): void
    {
        Category::query()->firstOrCreate(['slug' => 'pogrujnye'], [
            'parent_id' => 0,
            'name' => 'Погружные насосы',
            'is_active' => true,
        ]);

        $response = $this->get('https://skidel.kotlov.by/nasosy/pogrujnye/removed-product');

        $response->assertStatus(301);
        $response->assertRedirect('https://skidel.kotlov.by/pogrujnye');
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

    public function test_city_category_page_remains_regional(): void
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
        ]);

        $request = Request::create('https://gomel.kotlov.by/gazovye', 'GET');
        $response = app(HandleRedirects::class)->handle(
            $request,
            fn () => response('', 204),
        );

        $this->assertSame(204, $response->getStatusCode());
        $this->assertNull($response->headers->get('Location'));
    }

    public function test_exact_product_redirect_wins_over_broad_legacy_rules(): void
    {
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
        $response->assertRedirect('https://gomel.kotlov.by/teplovyie-nasosyi/kotlov-ge-flm30-r32-10-kvt');
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
        $response = $this->get('https://chechersk.kotlov.by/otoplenie-parts/grebenki');

        $response->assertStatus(301);
        $response->assertRedirect('https://chechersk.kotlov.by/grebenki');
    }

    public function test_legacy_discounts_category_redirects_to_promotions_page(): void
    {
        $response = $this->get('https://kotlov.by/aktsiiiskidki');

        $response->assertStatus(301);
        $response->assertRedirect('https://kotlov.by/akcii');
    }
}

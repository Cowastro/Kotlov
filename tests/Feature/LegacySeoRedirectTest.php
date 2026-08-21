<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Http\Middleware\HandleRedirects;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LegacySeoRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('redirects');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_url')->unique();
            $table->string('to_url');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_nested_legacy_category_redirects_to_current_flat_url(): void
    {
        Category::create([
            'parent_id' => 0,
            'name' => 'Центробежные насосы',
            'slug' => 'tsentrobejnye',
            'is_active' => true,
        ]);

        $response = $this->get('https://krugloe.kotlov.by/nasosy/poverhnostnyie/tsentrobejnye');

        $response->assertStatus(301);
        $response->assertRedirect('https://krugloe.kotlov.by/tsentrobejnye');
    }

    public function test_removed_legacy_product_falls_back_to_nearest_active_category(): void
    {
        Category::create([
            'parent_id' => 0,
            'name' => 'Погружные насосы',
            'slug' => 'pogrujnye',
            'is_active' => true,
        ]);

        $response = $this->get('https://skidel.kotlov.by/nasosy/pogrujnye/removed-product');

        $response->assertStatus(301);
        $response->assertRedirect('https://skidel.kotlov.by/pogrujnye');
    }

    public function test_canonical_product_path_is_not_collapsed_to_its_category(): void
    {
        $category = Category::create([
            'parent_id' => 0,
            'name' => 'Газовые котлы',
            'slug' => 'gazovye',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
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

    public function test_old_parts_prefix_is_removed_instead_of_rewritten_to_wrong_section(): void
    {
        $response = $this->get('https://chechersk.kotlov.by/otoplenie-parts/grebenki');

        $response->assertStatus(301);
        $response->assertRedirect('https://chechersk.kotlov.by/grebenki');
    }
}

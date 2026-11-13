<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class NavigationRedirectedCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_with_active_redirect_is_not_linked_from_navigation(): void
    {
        $parent = Category::query()->create([
            'parent_id' => 0,
            'name' => 'Водонагреватели теста',
            'slug' => 'redirect-nav-parent-test',
            'is_active' => true,
        ]);

        foreach (['redirect-nav-merged-test', 'redirect-nav-target-test', 'redirect-nav-inactive-test'] as $index => $slug) {
            $child = Category::query()->create([
                'parent_id' => $parent->id,
                'name' => 'Раздел '.$slug,
                'slug' => $slug,
                'is_active' => true,
            ]);

            Product::query()->create([
                'category_id' => $child->id,
                'name' => 'Товар '.$slug,
                'slug' => 'redirect-nav-product-'.$index,
                'sku' => 'REDIRECT-NAV-'.$index,
                'price' => 100,
                'is_active' => true,
                'is_archived' => false,
                'in_stock' => true,
                'availability_status' => Product::AVAILABILITY_IN_STOCK,
            ]);
        }

        DB::table('redirects')->insert([
            ['from_url' => '/redirect-nav-merged-test', 'to_url' => '/redirect-nav-target-test', 'status_code' => 301, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['from_url' => '/redirect-nav-inactive-test', 'to_url' => '/redirect-nav-target-test', 'status_code' => 301, 'is_active' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Cache::flush();

        $method = new ReflectionMethod(AppServiceProvider::class, 'getNavCategories');
        $navigation = $method->invoke(new AppServiceProvider($this->app));
        $slugs = $navigation->firstWhere('slug', $parent->slug)->children->pluck('slug')->all();

        $this->assertNotContains('redirect-nav-merged-test', $slugs);
        $this->assertContains('redirect-nav-target-test', $slugs);
        $this->assertContains('redirect-nav-inactive-test', $slugs);
    }

    public function test_contacts_page_has_organization_schema(): void
    {
        $html = $this->get('/contacts')->assertOk()->getContent();

        $this->assertStringContainsString('"@id":"https://kotlov.by/#organization"', $html);
        $this->assertStringContainsString('"taxID":"191355768"', $html);
        $this->assertStringContainsString('"telephone":"+375293777092"', $html);
    }
}

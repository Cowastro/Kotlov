<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
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

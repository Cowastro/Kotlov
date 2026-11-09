<?php

namespace Tests\Feature;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandPageMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_page_fills_the_city_and_shares_its_logo(): void
    {
        Brand::query()->create([
            'name' => 'UNIVAT', 'slug' => 'univat-meta-test', 'is_active' => true,
            'logo' => 'img/brands/univat.png',
            'meta_title' => 'Электрокотлы UNIVAT — купить в %city%, цены',
            'meta_description' => 'Электрокотлы UNIVAT с доставкой в %city%.',
        ]);

        $html = $this->get('/brands/univat-meta-test')->assertOk()->getContent();

        $this->assertStringNotContainsString('%city%', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.url('img/brands/univat.png').'">', $html);
    }
}

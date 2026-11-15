<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_and_suggestions_accept_malformed_query_parameters_without_server_errors(): void
    {
        $this->get('/search?q[]=bad&category[]=bad&sort[]=bad')->assertOk()
            ->assertViewHas('q', '')->assertViewHas('category', '')->assertViewHas('sort', '');
        $this->getJson('/search/suggest?q[]=bad')->assertOk()->assertExactJson([]);
        $this->get('/search?'.http_build_query(['q' => str_repeat('я', 150)]))->assertOk()
            ->assertViewHas('q', str_repeat('я', 100));
    }

    public function test_catalog_renders_each_product_once_with_details_and_existing_actions(): void
    {
        $category = Category::create(['name' => 'Печи', 'slug' => 'audit-stoves', 'parent_id' => 0, 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Audit stove', 'slug' => 'audit-stove', 'sku' => 'AUDIT-1',
            'is_active' => true, 'is_archived' => false, 'price' => 100, 'short_description' => 'Verified manufacturer description.',
        ]);
        $response = $this->get('/audit-stoves')->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $cards = $xpath->query('//*[@id="gridLayout"]/*[contains(concat(" ",normalize-space(@class)," ")," card-product ")]');
        $this->assertCount(1, $cards);
        $this->assertCount(0, $xpath->query('//*[@id="listLayout"]'));
        foreach (['wishlist', 'compare'] as $class) {
            $this->assertCount(1, $xpath->query('.//li[contains(concat(" ",normalize-space(@class)," ")," '.$class.' ")]', $cards->item(0)));
        }
        $response->assertSee('Verified manufacturer description.')->assertSee('AUDIT-1')
            ->assertSee('data-product-id="'.$product->id.'"', false)
            ->assertSee('catalog-layout.css', false);
    }

    public function test_catalog_index_has_one_h1_and_organization_image_exists(): void
    {
        $response = $this->get('/catalog')->assertOk();
        $this->assertSame(1, preg_match_all('/<h1\b/i', $response->getContent()));
        $home = $this->get('/')->assertOk();
        $home->assertDontSee('img/og-default.jpg', false)->assertSee('img/og/kotlov-default.jpg', false);
        $this->assertFileExists(public_path('img/og/kotlov-default.jpg'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\StorefrontProductSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_hydraulic_separator_synonyms_and_brand_order_work_in_suggestions_and_search(): void
    {
        $category = Category::create(['name' => 'Разделители', 'slug' => 'search-separators', 'parent_id' => 0, 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'OLE-PRO Гидравлический разделитель ОГС-Р-3-НР-i',
            'slug' => 'ole-pro-search', 'sku' => 'SEARCH-SEP', 'price' => 379.18,
            'is_active' => true, 'is_archived' => false, 'availability_status' => 'check',
        ]);
        foreach (['гидрострелка', 'гидрострелка ole pro', 'ole-pro гидрострелка', 'разделитель OLE', 'гидравлический разделитель'] as $term) {
            $this->assertTrue(app(StorefrontProductSearch::class)->apply(Product::query(), $term)->whereKey($product->id)->exists(), $term);
            $this->getJson('/search/suggest?'.http_build_query(['q' => $term]))
                ->assertOk()->assertJsonFragment(['name' => $product->name]);
        }
        $this->get('/search?'.http_build_query(['q' => 'гидрострелка ole pro']))
            ->assertOk()->assertSee($product->name);
        $this->assertFalse(app(StorefrontProductSearch::class)->apply(Product::query(), 'гидрострелка bosch')->whereKey($product->id)->exists());
    }
}

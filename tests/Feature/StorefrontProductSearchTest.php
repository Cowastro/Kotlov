<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\StorefrontProductSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_gas_meter_search_ignores_word_order_and_understands_word_forms(): void
    {
        $category = Category::create([
            'name' => 'Счетчики газа',
            'slug' => 'search-gas-meters',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $meter = Product::create([
            'category_id' => $category->id,
            'name' => 'Счетчик газа ультразвуковой СКАТ G4',
            'slug' => 'skat-g4-smart-search',
            'sku' => 'SKAT-G4-SEARCH',
            'price' => 470,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $boilerCategory = Category::create([
            'name' => 'Газовые котлы',
            'slug' => 'search-gas-boilers',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $boiler = Product::create([
            'category_id' => $boilerCategory->id,
            'name' => 'Газовый котел контрольный',
            'slug' => 'gas-boiler-search-control',
            'sku' => 'GAS-BOILER-SEARCH',
            'price' => 1000,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $search = app(StorefrontProductSearch::class);

        foreach (['газовый счетчик', 'счетчик газовый', 'газовые счетчики', 'газовый счётчик'] as $term) {
            $matches = $search->apply(Product::query(), $term)->pluck('id');
            $this->assertTrue($matches->contains($meter->id), $term);
            $this->assertFalse($matches->contains($boiler->id), $term);
        }

        $this->getJson('/search/suggest?'.http_build_query(['q' => 'газовый счетчик']))
            ->assertOk()
            ->assertJsonFragment(['type' => 'category', 'name' => 'Счетчики газа'])
            ->assertJsonFragment(['type' => 'product', 'name' => $meter->name]);

        $this->get('/search?'.http_build_query(['q' => 'газовый счетчик']))
            ->assertOk()
            ->assertSee($meter->name)
            ->assertDontSee($boiler->name);
    }

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

    public function test_search_understands_brand_transliteration_keyboard_layout_and_cyrillic_model_codes(): void
    {
        $category = Category::create(['name' => 'Камины', 'slug' => 'search-fireplaces', 'parent_id' => 0, 'is_active' => true]);
        $latinBrand = Brand::create(['name' => 'Fenix', 'slug' => 'fenix-search', 'is_active' => true]);
        $cyrillicBrand = Brand::create(['name' => 'Феникс', 'slug' => 'feniks-search', 'is_active' => true]);
        $latinBrandProduct = Product::create([
            'category_id' => $category->id, 'brand_id' => $latinBrand->id,
            'name' => 'Каминная топка Classic 700', 'slug' => 'fenix-latin-search',
            'sku' => 'FNX-700', 'price' => 1000, 'is_active' => true, 'is_archived' => false,
        ]);
        $cyrillicBrandProduct = Product::create([
            'category_id' => $category->id, 'brand_id' => $cyrillicBrand->id,
            'name' => 'Печь-камин Optima', 'slug' => 'fenix-cyrillic-search',
            'sku' => 'OPTIMA', 'price' => 1200, 'is_active' => true, 'is_archived' => false,
        ]);
        $modelProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Стабилизатор напряжения АРС-500', 'slug' => 'ars-500-search',
            'sku' => 'АРС 500', 'price' => 200, 'is_active' => true, 'is_archived' => false,
        ]);
        $search = app(StorefrontProductSearch::class);

        $this->assertTrue($search->apply(Product::query(), 'Феникс')->whereKey($latinBrandProduct)->exists());
        $this->assertTrue($search->apply(Product::query(), 'Fenix')->whereKey($cyrillicBrandProduct)->exists());
        $this->assertTrue($search->apply(Product::query(), 'APC-500')->whereKey($modelProduct)->exists());
        $this->assertTrue($search->apply(Product::query(), 'atybrc')->whereKey($cyrillicBrandProduct)->exists());

        $this->getJson('/search/suggest?q=Fenix')
            ->assertOk()
            ->assertJsonFragment(['name' => $cyrillicBrandProduct->name]);
        $this->get('/search?q=APC-500')
            ->assertOk()
            ->assertSee($modelProduct->name);
        $this->getJson('/search/suggest?q=kaminy')
            ->assertOk()
            ->assertJsonFragment(['type' => 'category', 'name' => 'Камины']);
    }
}

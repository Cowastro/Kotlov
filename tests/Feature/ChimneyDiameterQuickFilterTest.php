<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\ChimneyDiameterQuickFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChimneyDiameterQuickFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_diameter_pairs_do_not_confuse_grades_mono_reducers_or_ranges(): void
    {
        $filter = new ChimneyDiameterQuickFilter;
        foreach ([
            'Труба термо ТТ-Р 430, 0.8/430, 0.5, Ø 120/180 L1000' => '120/180',
            'Отвод ОТ-Р 45* 430, 0.8/430, 0.5, Ø 120/220' => '120/220',
            'Переход Моно/Термо ПМТ-Р (430-1,0) 150/210' => '150/210',
            'Адаптер-переход Моно М-М 430-0.8 Ø 120/180' => null,
            'Проход ППУ 500х500 Ø 180-220' => null,
            'Труба моно Ø120' => null,
            'Труба термо 430, 0.8/430, 0.5' => null,
        ] as $name => $pair) {
            $this->assertSame($pair, $filter->diameterPair($name), $name);
        }
    }

    public function test_catalog_filters_exact_pairs_preserves_brand_and_excludes_archived_products(): void
    {
        $category = Category::updateOrCreate(['slug' => 'dymohody'], ['name' => 'Дымоходы', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::create(['name' => 'Тест Теплов', 'slug' => 'chimney-test', 'is_active' => true]);
        $other = Brand::create(['name' => 'Другой', 'slug' => 'chimney-other', 'is_active' => true]);
        foreach ([
            ['Труба термо Ø120/180 L1000', $brand->id, false],
            ['Труба термо Ø120/220 L1000', $brand->id, false],
            ['Адаптер Моно Ø120/180', $brand->id, false],
            ['Архив термо Ø120/180', $brand->id, true],
            ['Чужой термо Ø120/180', $other->id, false],
        ] as $index => [$name, $brandId, $archived]) {
            Product::create([
                'name' => $name, 'slug' => 'chimney-quick-'.$index, 'sku' => 'CHIMNEY-QUICK-'.$index,
                'brand_id' => $brandId, 'category_id' => $category->id, 'price' => 100,
                'is_active' => true, 'is_archived' => $archived,
            ]);
        }

        $this->get('/dymohody?'.http_build_query(['brand' => $brand->id, 'chimney_diameter' => '120/180']))
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 1 && $products->first()->name === 'Труба термо Ø120/180 L1000')
            ->assertSee('120/180 мм')->assertSee('120/220 мм')
            ->assertSee('brand='.$brand->id, false)
            ->assertSee('aria-current="true"', false);

        $this->get('/dymohody?'.http_build_query(['brand' => $brand->id, 'chimney_diameter' => '999/1000']))
            ->assertOk()->assertViewHas('products', fn ($products) => $products->total() === 0);
    }

    public function test_quick_links_keep_other_filters_and_reset_pagination(): void
    {
        request()->merge(['brand' => 292, 'price_min' => 30, 'page' => 3, 'chimney_diameter' => '120/180']);
        $html = view('partials.chimney-diameter-quick-filters', [
            'chimneyDiameterGroups' => collect(['120/180' => collect([1, 2]), '120/220' => collect([3])]),
        ])->render();
        $this->assertStringContainsString('brand=292', $html);
        $this->assertStringContainsString('price_min=30', $html);
        $this->assertStringNotContainsString('page=3', $html);
        $this->assertStringContainsString('chimney_diameter=120%2F220', $html);
        $this->assertStringContainsString('Все диаметры', $html);
    }
}

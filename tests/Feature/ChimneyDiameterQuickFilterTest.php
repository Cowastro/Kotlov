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

    public function test_steel_is_inner_tube_material_not_outer_casing_or_diameter(): void
    {
        $filter = new ChimneyDiameterQuickFilter;
        $this->assertSame('304', $filter->steelGrade('Труба термо 304-0.8/430-0.5 Ø120/180'));
        $this->assertSame('430', $filter->steelGrade('Труба термо 430, 0.8/304, 0.5 Ø120/180'));
        $this->assertSame('316L', $filter->steelGrade('Труба моно AISI 316L D120'));
        $this->assertSame('316', $filter->steelGrade('Труба термо Ø120/180', ['Марка стали внутренней трубы' => 'AISI 316', 'Марка стали наружной трубы' => 'AISI 430']));
        $this->assertNull($filter->steelGrade('Труба моно Ø304 L1000'));
        $this->assertNull($filter->steelGrade('Труба Ø120', ['Материал наружного кожуха' => 'AISI 430']));
    }

    public function test_steel_and_diameter_work_together_without_using_outer_casing_grade(): void
    {
        $category = Category::updateOrCreate(['slug' => 'dymohody'], ['name' => 'Дымоходы', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::create(['name' => 'Тест стали', 'slug' => 'steel-test', 'is_active' => true]);
        foreach (['Труба термо 304-0.8/430-0.5 Ø120/180', 'Труба термо 430-0.8/430-0.5 Ø120/180', 'Труба термо 304-0.8/430-0.5 Ø150/210'] as $index => $name) {
            Product::create([
                'name' => $name, 'slug' => 'steel-test-'.$index, 'sku' => 'STEEL-TEST-'.$index,
                'category_id' => $category->id, 'brand_id' => $brand->id, 'price' => 100,
                'is_active' => true, 'is_archived' => false,
            ]);
        }
        $this->get('/dymohody?'.http_build_query(['brand' => $brand->id, 'chimney_diameter' => '120/180', 'chimney_steel' => '304']))
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 1 && $products->first()->name === 'Труба термо 304-0.8/430-0.5 Ø120/180')
            ->assertViewHas('chimneySteelGroups', fn ($groups) => $groups->get('304')->count() === 1 && $groups->get('430')->count() === 1)
            ->assertSee('AISI 304')->assertSee('AISI 430')
            ->assertSee('chimney_diameter=120%2F180&amp;chimney_steel=430', false);
    }

    public function test_mono_diameters_exclude_pairs_ranges_and_sandwich_elements(): void
    {
        $filter = new ChimneyDiameterQuickFilter;
        foreach ([
            'Труба моно ТМ-Р 430, 0.8, Ø 120 L1000' => '120',
            'Теплов и Сухов Конденсатоотвод моно КМ-Р 430-0.5 D80 М' => '80',
            'Зонт ЗМ-Р 430, 0.5, Ø200' => '200',
            'Труба моно Ф115' => '115',
            'Труба моно D1200' => null,
            'Адаптер Моно Ø120/180' => null,
            'Переход Моно/Термо Ø120/180' => null,
            'Труба термо Ø120/180' => null,
            'Крепление моно Ø120-180' => null,
            'Труба моно 430 0.8 L1000' => null,
        ] as $name => $diameter) {
            $this->assertSame($diameter, $filter->monoDiameter($name), $name);
        }
    }

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
            'Конвектор трубный КТр-Р L1000 AISI 430 0,8 мм Ø115/120' => null,
            'Адаптер Ø115/150' => null,
            'Конус Теплов и Сухов КТ-Р 430, 0.5/430, 0.5, Ø120/180' => '120/180',
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
            ['Труба моно Ø120 L1000', $brand->id, false],
            ['Труба моно Ø150 L1000', $brand->id, false],
            ['Чужой моно Ø120', $other->id, false],
            ['Архив моно Ø120', $brand->id, true],
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

        $this->get('/dymohody?'.http_build_query(['brand' => $brand->id, 'mono_diameter' => '120']))
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 1 && $products->first()->name === 'Труба моно Ø120 L1000')
            ->assertSee('Моно: быстрый выбор диаметра')->assertSee('150 мм')
            ->assertSee('mono_diameter=150', false);
    }

    public function test_switching_to_mono_removes_sandwich_size_and_keeps_brand_and_sort(): void
    {
        request()->merge(['brand' => 292, 'sort' => 'price_asc', 'page' => 3, 'chimney_diameter' => '120/180']);
        $html = view('partials.chimney-diameter-quick-filters', [
            'isMonoQuickFilter' => true,
            'chimneyDiameterGroups' => collect([120 => collect([1]), 150 => collect([2])]),
        ])->render();
        $this->assertStringContainsString('mono_diameter=120', $html);
        $this->assertStringContainsString('brand=292', $html);
        $this->assertStringContainsString('sort=price_asc', $html);
        $this->assertStringNotContainsString('chimney_diameter=', $html);
        $this->assertStringNotContainsString('page=3', $html);
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

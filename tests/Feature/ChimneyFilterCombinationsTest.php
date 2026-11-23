<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogSpecificationFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChimneyFilterCombinationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_sixteen_combinations_keep_matching_cards_and_exclude_only_actual_mismatches(): void
    {
        $category = Category::updateOrCreate(['slug' => 'dymohody'], ['name' => 'Дымоходы', 'parent_id' => 0, 'is_active' => true]);
        // Data migrations publish catalogue cards; this matrix must use only its own fixtures.
        Product::query()->update(['is_active' => false]);
        $attributes = [];
        foreach (['Диаметр' => ['115 мм', '150 мм'], 'Тип металла' => ['Нержавеющая сталь 304', 'Нержавеющая сталь 430'], 'Толщина металла' => ['0.8', '1']] as $name => $values) {
            $attribute = Attribute::create(['category_id' => $category->id, 'name' => $name, 'type' => 'select', 'in_filter' => true, 'suffix' => $name === 'Толщина металла' ? 'мм' : null]);
            foreach ($values as $value) {
                $attribute->options()->create(['name' => $value]);
            }
            $attributes[$name] = $attribute;
        }
        $fixtures = [[115, '430', '0.8', true], [115, '304', '0.8', true], [150, '430', '0.8', true], [115, '430', '1', true], [115, '430', '0.8', false]];
        $cards = [];
        foreach ($fixtures as $index => [$diameter, $grade, $thickness, $stock]) {
            $product = Product::create(['category_id' => $category->id, 'name' => "Шибер-задвижка {$grade}-{$thickness}, Ø {$diameter}",
                'slug' => 'combination-'.$index, 'price' => 100, 'is_active' => true, 'is_archived' => false,
                'in_stock' => $stock, 'availability_status' => Product::AVAILABILITY_CHECK,
                'specs' => ['Диаметр' => $diameter.' мм', 'Марка стали' => $grade]]);
            app(CatalogSpecificationFilters::class)->sync($product);
            $cards[$product->id] = [$diameter, $grade, $thickness, $stock];
        }
        $thickness = $attributes['Толщина металла'];
        $option = $thickness->options()->where('name', '0.8')->value('id');
        for ($mask = 0; $mask < 16; $mask++) {
            $params = [];
            if ($mask & 1) {
                $params['mono_diameter'] = '115';
            }
            if ($mask & 2) {
                $params['chimney_steel'] = '430';
            }
            if ($mask & 4) {
                $params['attr'] = [$thickness->id => [$option]];
            }
            if ($mask & 8) {
                $params['in_stock'] = '1';
            }
            $expected = collect($cards)->filter(fn ($facts) => (! ($mask & 1) || $facts[0] === 115)
                && (! ($mask & 2) || $facts[1] === '430') && (! ($mask & 4) || $facts[2] === '0.8')
                && (! ($mask & 8) || $facts[3]))->keys()->sort()->values()->all();
            $response = $this->get('/dymohody?'.http_build_query($params))->assertOk();
            $this->assertSame($expected, $response->viewData('products')->pluck('id')->sort()->values()->all(), 'Filter combination '.$mask);
        }
        $diameter = $attributes['Диаметр'];
        $steel = $attributes['Тип металла'];
        $this->get('/dymohody?'.http_build_query(['attr' => [
            $diameter->id => [$diameter->options()->where('name', '115 мм')->value('id')],
            $steel->id => [$steel->options()->where('name', 'Нержавеющая сталь 430')->value('id')],
            $thickness->id => [$option],
        ], 'in_stock' => '1']))->assertOk()
            ->assertViewHas('chimneySteelGroups', fn ($groups) => $groups->has('304') && $groups->has('430'))
            ->assertViewHas('monoChimneyDiameterGroups', fn ($groups) => $groups->has(115) && $groups->has(150));
    }

    public function test_quick_links_replace_the_same_sidebar_filter_and_preserve_other_selections(): void
    {
        request()->merge(['attr' => [435 => [272], 441 => [1378], 439 => [1168]], 'in_stock' => '1', 'page' => 4]);
        $attributes = collect([(object) ['id' => 435, 'name' => 'Диаметр', 'all_ids' => [435]], (object) ['id' => 441, 'name' => 'Тип металла', 'all_ids' => [441]]]);
        $diameters = view('partials.chimney-diameter-quick-filters', ['filterAttributes' => $attributes, 'isMonoQuickFilter' => true, 'chimneyDiameterGroups' => collect([150 => collect([1])])])->render();
        $this->assertStringNotContainsString('attr%5B435', $diameters);
        $this->assertStringContainsString('attr%5B441', $diameters);
        $this->assertStringContainsString('attr%5B439', $diameters);
        $steel = view('partials.chimney-steel-quick-filters', ['filterAttributes' => $attributes, 'chimneySteelGroups' => collect(['304' => collect([1])])])->render();
        $this->assertStringNotContainsString('attr%5B441', $steel);
        $this->assertStringContainsString('attr%5B435', $steel);
        $this->assertStringContainsString('in_stock=1', $steel);
        $this->assertStringNotContainsString('page=4', $steel);
    }
}

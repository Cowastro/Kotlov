<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoilerCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_wall_mounted_combination_landing_and_catalog_search(): void
    {
        $gas = $this->category('gazovye');
        $electric = $this->category('elektricheskie');
        $brand = Brand::query()->create(['name' => 'HeatBrand', 'slug' => 'heat-brand']);
        $this->boiler($gas, 'Model-A', ['Установка' => 'Настенная', 'Количество контуров' => '2', 'Мощность котла' => '24 кВт'], $brand);
        $this->boiler($gas, 'Model-B', ['Установка' => 'Напольная', 'Количество контуров' => '2', 'Мощность котла' => '35 кВт'], $brand);
        $this->boiler($electric, 'Model-C', [], $brand);

        $this->get('/gazovye/nastennye-dvuhkonturnye')->assertOk()
            ->assertViewHas('title', fn ($title) => str_contains($title, 'Настенные двухконтурные газовые котлы'))
            ->assertViewHas('products', fn ($products) => $products->pluck('name')->all() === ['Model-A'])
            ->assertViewHas('canonical', fn ($canonical) => str_ends_with($canonical, '/gazovye/nastennye-dvuhkonturnye'));

        $this->get('/gazovye?q=HeatBrand+SKU-Model-A')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('name')->all() === ['Model-A'])
            ->assertViewHas('robots', 'noindex, follow')
            ->assertSee('24 кВт');
    }

    public function test_facets_use_real_values_and_count_only_matching_products(): void
    {
        $electric = $this->category('elektricheskie');
        $this->boiler($electric, 'Network A', ['Напряжение сети' => '220/380 В', 'Мощность' => '6 кВт', 'Площадь отопления' => '80 м²']);
        $this->boiler($electric, 'Network B', ['Напряжение сети' => '380 В', 'Мощность' => '12 кВт', 'Площадь отопления' => '130 м³']);
        $this->boiler($electric, 'Network C', ['Мощность' => '12 кВт']);

        $this->get('/elektricheskie?boiler[voltage]=380')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 2)
            ->assertViewHas('boilerFacets', function ($facets) {
                return $facets['voltage']['options'][220]['count'] === 1
                    && $facets['voltage']['options'][380]['count'] === 2
                    && array_keys($facets['area']['options']) === ['to-90'];
            });

        $this->get('/elektricheskie?boiler[voltage]=380&boiler[power]=to-12')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('name')->all() === ['Network B'])
            ->assertViewHas('boilerFacets', fn ($facets) => ! isset($facets['area']));
    }

    public function test_pellet_power_filter_accepts_the_old_url_and_power_from_specs(): void
    {
        $pellet = $this->category('kotly-na-pelletah');
        $brand = Brand::query()->create(['name' => 'Pellet Test', 'slug' => 'pellet-test']);
        $this->boiler($pellet, 'Pellet A', ['Номинальная мощность' => '25 кВт', 'Вместимость бункера' => '80 кг'], $brand);
        $this->boiler($pellet, 'Pellet B', ['Номинальная мощность' => '25,5 кВт', 'Объём бункера' => '80 л'], $brand);

        $this->get('/kotly-na-pelletah?power=up-to-25&brand='.$brand->id)->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('name')->all() === ['Pellet A'])
            ->assertViewHas('boilerFacets', fn ($facets) => $facets['power']['options']['up-to-25']['active']
                && ! str_contains($facets['power']['options']['up-to-25']['url'], 'power='));

        $this->get('/kotly-na-pelletah?boiler[hopper]=to-100&brand='.$brand->id)->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('name')->all() === ['Pellet A']);
    }

    private function category(string $slug): Category
    {
        return Category::query()->firstOrCreate(['slug' => $slug], ['name' => $slug, 'is_active' => true]);
    }

    private function boiler(Category $category, string $name, array $specs, ?Brand $brand = null): void
    {
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand?->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'sku' => 'SKU-'.$name,
            'price' => 1000,
            'is_active' => true,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'specs' => $specs,
        ]);
    }
}

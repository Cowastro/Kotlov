<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaterHeaterCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_water_heater_hub_and_gas_listing_keep_product_addresses(): void
    {
        $root = $this->category('vodonagrevateli');
        $gas = $this->category('gas', $root);
        $column = $this->category('vodogreynaya-kolonka', $root);
        $buffer = $this->category('bufernye-emkosti', $root);
        $parts = $this->category('komplektuyushchie-dlya-vodonagrevateley', $root);

        $model = $this->product($gas, 'QA-Gas-Model', ['Производительность, л/мин' => 11, 'Камера сгорания' => 'Закрытая']);
        $this->product($column, 'QA-Gas-Column', ['Производительность' => '9,4 л/мин']);
        $this->product($buffer, 'QA-Gas-Buffer', ['Объём, л' => 500]);
        $this->product($parts, 'QA-Gas-Part', []);

        $this->get('/vodonagrevateli?q=QA-Gas')->assertOk()
            ->assertSee('Типы водонагревателей')
            ->assertSeeText('Запчасти и комплектующие')
            ->assertViewHas('products', fn ($products) => $products->pluck('name')->sort()->values()->all() === ['QA-Gas-Column', 'QA-Gas-Model']);

        $this->get('/gas?in_stock=1')->assertRedirect('/vodogreynaya-kolonka?in_stock=1');
        $this->get('/vodonagrevateli?subcategory=bufernye-emkosti')->assertRedirect('/bufernye-emkosti');
        $this->get('/vodogreynaya-kolonka?heater[chamber]=closed')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->contains($model->id))
            ->assertSee('11 л/мин');
        $this->get('/gas/'.$model->slug)->assertOk();
    }

    public function test_electric_landings_separate_tank_capacity_and_flow(): void
    {
        $category = $this->category('electric');
        $storage = $this->product($category, 'QA-Tank', [
            'Объем бака, л' => '80 л',
            'Номинальная мощность, кВт' => '2,5 кВт',
            'Мощность нагрева' => '1 кВт',
            'Тип установки' => 'настенный',
        ]);
        $flow = $this->product($category, 'QA-Flow', [
            'Тип нагрева' => 'Проточный',
            ['key' => 'Максимальная производительность ГВС', 'value' => '5,2', 'unit' => 'л/мин'],
            ['key' => 'Мощность', 'value' => '6000', 'unit' => 'Вт'],
        ]);
        $this->product($category, 'QA-Unknown', [['key' => 'Производительность', 'value' => '80', 'unit' => 'л/ч'], ['key' => 'Объём', 'value' => '1', 'unit' => 'м³']]);

        $this->get('/electric/nakopitelnye?q=QA')->assertOk()
            ->assertSee('2,5 кВт')
            ->assertSee('80 л')
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$storage->id])
            ->assertViewHas('specificationFacets', fn ($facets) => ! isset($facets['flow']));
        $this->get('/electric/protochnye?q=QA')->assertOk()
            ->assertSee('5,2 л/мин')
            ->assertSee('6 кВт')
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$flow->id])
            ->assertViewHas('specificationFacets', fn ($facets) => ! isset($facets['volume']));
        $this->get('/electric?q=QA&heater[volume]=to-80')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->pluck('id')->all() === [$storage->id])
            ->assertViewHas('robots', 'noindex, follow');
    }

    private function category(string $slug, ?Category $parent = null): Category
    {
        return Category::query()->updateOrCreate(['slug' => $slug], [
            'name' => $slug,
            'parent_id' => $parent?->id ?? 0,
            'is_active' => true,
        ]);
    }

    private function product(Category $category, string $name, array $specs): Product
    {
        return Product::query()->create([
            'name' => $name, 'slug' => str($name)->slug(), 'sku' => $name,
            'category_id' => $category->id, 'price' => 120, 'is_active' => true,
            'availability_status' => Product::AVAILABILITY_CHECK, 'specs' => $specs,
        ]);
    }
}

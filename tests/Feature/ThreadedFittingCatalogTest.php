<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\ProductAttributeValue;
use App\Models\City;
use App\Models\Brand;
use App\Support\Catalog\ThreadedFittingCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThreadedFittingCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function fitting(string $name, array $specs = []): Product
    {
        $root = Category::firstOrCreate(['slug' => 'truby-i-fitingi'], ['name' => 'Трубы и фитинги', 'parent_id' => 0, 'is_active' => true]);
        $category = Category::firstOrCreate(['slug' => ThreadedFittingCatalog::ROOT], ['name' => 'Резьбовые фитинги', 'parent_id' => $root->id, 'is_active' => true]);

        return Product::create([
            'name' => $name, 'slug' => 'fitting-'.Product::count(), 'sku' => 'FIT-'.Product::count(),
            'category_id' => $category->id, 'price' => 100, 'is_active' => true, 'is_archived' => false, 'specs' => $specs,
        ]);
    }

    public function test_type_picker_combines_with_material_search_and_price_without_moving_products(): void
    {
        $elbow = $this->fitting('Угольник латунный переходной');
        $this->fitting('Угольник чугунный');
        $this->fitting('Тройник латунный переходной');
        $this->fitting('Ниппель латунный переходной');
        foreach (['/rezbovye-fitingi/latunnye?fitting[type]=ugolki', '/rezbovye-fitingi?fitting[material]=latunnye&fitting[type]=ugolki&price_max=150'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('Подбор по типу фитинга')->assertSee('Уголки и угольники')
                ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$elbow->id]);
        }
        $this->get('/rezbovye-fitingi/latunnye?q=уголок')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$elbow->id]);
        $this->get('/rezbovye-fitingi?fitting[type]=nippeli')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 1)
            ->assertSee('/rezbovye-fitingi/latunnye?fitting%5Btype%5D=nippeli', false);
    }

    public function test_types_use_explicit_data_and_unambiguous_name_heads(): void
    {
        $catalog = new ThreadedFittingCatalog;
        foreach ([
            ['Угол переходной', [], 'ugolki'],
            ['Тройник переходной', [], 'troiniki'],
            ['Ниппель переходной', [], 'nippeli'],
            ['Муфта с ниппелем', [], 'mufty'],
            ['Соединитель латунный (американка) угловой', [], 'amerikanki'],
            ['Фитинг', ['Вид фитинга' => 'отвод (колено, угольник)'], 'ugolki'],
            ['Фитинг', ['Вид фитинга' => 'Сдвоенный', 'Тип фитинга' => 'Ниппель'], 'nippeli'],
            ['Муфта', ['Вид фитинга' => 'тройник', 'Тип фитинга' => 'ниппель'], 'unknown'],
            ['Комплект заглушек', [], 'zaglushki'],
            ['Латунный запирающий колпачок', [], 'zaglushki'],
            ['Переходник', ['Вид фитинга' => 'фитинг'], 'perehodniki'],
            ['Набор с угольником и ниппелем', [], 'unknown'],
        ] as [$name, $specs, $expected]) {
            $fact = $catalog->productFacts(new Product(compact('name', 'specs')), $catalog::ROOT, []);
            $this->assertSame([$expected], $fact['options']['type'], $name);
        }
    }

    public function test_type_can_come_from_an_attribute_and_bad_filter_values_are_ignored(): void
    {
        $product = $this->fitting('Фитинг латунный');
        $attribute = Attribute::create(['category_id' => $product->category_id, 'name' => 'Вид фитинга', 'type' => 'select', 'in_filter' => true]);
        $option = AttributeOption::create(['attribute_id' => $attribute->id, 'name' => 'тройник']);
        ProductAttributeValue::create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'option_id' => $option->id]);
        $this->get('/rezbovye-fitingi?fitting[type]=troiniki')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$product->id]);
        $this->get('/rezbovye-fitingi?fitting[type][]=troiniki')->assertOk();
        $this->get('/rezbovye-fitingi?fitting[type]=invalid')->assertOk();
        $this->get('/rezbovye-fitingi?fitting[type]=nippeli')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 0);
    }

    public function test_material_landings_do_not_mix_metals_or_change_product_urls(): void
    {
        $brass = $this->fitting('Муфта латунная');
        $iron = $this->fitting('Муфта чугунная');
        $steel = $this->fitting('Сгон стальной');
        $unknown = $this->fitting('Муфта хромированная');
        foreach (['latunnye' => $brass, 'chugunnye' => $iron, 'stalnye' => $steel] as $slug => $product) {
            $response = $this->get('https://kotlov.by/rezbovye-fitingi/'.$slug)->assertOk()
                ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$product->id]);
            $response->assertSee('href="/rezbovye-fitingi/'.$product->slug.'"', false)
                ->assertSee('<link rel="canonical" href="https://kotlov.by/rezbovye-fitingi/'.$slug.'">', false);
            $this->assertSame('rezbovye-fitingi', $product->fresh()->category->slug);
        }
        $this->get('/rezbovye-fitingi?fitting[material]=unknown')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$unknown->id]);
        $this->get('/rezbovye-fitingi')->assertOk()->assertViewHas('products', fn ($products) => $products->total() === 4);
    }

    public function test_explicit_material_wins_and_plating_or_conflicting_metals_are_not_guessed(): void
    {
        $catalog = new ThreadedFittingCatalog;
        foreach ([
            ['Муфта никелированная', [], 'unknown'],
            ['Муфта латунная', ['Материал' => 'чугун'], 'chugunnye'],
            ['Муфта', [['key' => 'Материал корпуса', 'value' => 'латунь'], ['key' => 'Материал уплотнения', 'value' => 'сталь']], 'latunnye'],
            ['Муфта', ['Материал' => 'латунь и сталь'], 'unknown'],
            ['Муфта латунная', ['Материал' => 'полипропилен'], 'unknown'],
            ['Муфта из нержавеющей стали', [], 'stalnye'],
        ] as [$name, $specs, $expected]) {
            $fact = $catalog->productFacts(new Product(compact('name', 'specs')), ThreadedFittingCatalog::ROOT, []);
            $this->assertSame([$expected], $fact['options']['material']);
        }
    }

    public function test_navigation_and_sitemap_expose_only_populated_material_sections(): void
    {
        $this->fitting('Муфта латунная');
        $this->fitting('Муфта чугунная');
        $this->get('/catalog')->assertOk()->assertSee('/rezbovye-fitingi/latunnye', false)
            ->assertSee('/rezbovye-fitingi/chugunnye', false)->assertDontSee('/rezbovye-fitingi/stalnye', false);
        $this->get('https://kotlov.by/sitemap.xml')->assertOk()->assertSee('/rezbovye-fitingi/latunnye', false)
            ->assertSee('/rezbovye-fitingi/chugunnye', false)->assertDontSee('/rezbovye-fitingi/stalnye', false);
        $this->get('/rezbovye-fitingi/stalnye')->assertNotFound();
    }

    public function test_material_selection_combines_with_price_and_search_and_updates_after_import(): void
    {
        $first = $this->fitting('Муфта латунная');
        $other = $this->fitting('Муфта латунная большая');
        $other->update(['price' => 200]);
        $this->get('/rezbovye-fitingi/latunnye?price_max=150&q=Муфта')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$first->id]);
        $first->update(['specs' => ['Материал' => 'чугун']]);
        $this->travel(301)->seconds();
        $this->get('/rezbovye-fitingi/chugunnye')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$first->id]);
        $this->travelBack();
    }

    public function test_existing_material_attributes_are_used_without_guessing_from_finish(): void
    {
        $product = $this->fitting('Муфта оцинкованная');
        $attribute = Attribute::create(['category_id' => $product->category_id, 'name' => 'Материал', 'type' => 'select', 'in_filter' => true]);
        $option = AttributeOption::create(['attribute_id' => $attribute->id, 'name' => 'Чугун']);
        ProductAttributeValue::create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'option_id' => $option->id]);
        $this->get('/rezbovye-fitingi/chugunnye')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$product->id]);
    }

    public function test_disabled_city_redirect_retains_material_selection(): void
    {
        $this->fitting('Муфта латунная');
        City::create(['slug' => 'gomel', 'name' => 'Гомель', 'name_in' => 'в Гомеле', 'name_title' => 'Гомеле', 'is_active' => false]);
        $this->get('https://gomel.kotlov.by/rezbovye-fitingi/latunnye')->assertRedirect('https://kotlov.by/rezbovye-fitingi/latunnye');
    }

    public function test_brand_choices_belong_to_the_selected_material(): void
    {
        $brass = $this->fitting('Муфта латунная');
        $iron = $this->fitting('Муфта чугунная');
        $brassBrand = Brand::create(['name' => 'Brass brand', 'slug' => 'brass-brand']);
        $ironBrand = Brand::create(['name' => 'Iron brand', 'slug' => 'iron-brand']);
        $brass->update(['brand_id' => $brassBrand->id]);
        $iron->update(['brand_id' => $ironBrand->id]);
        foreach (['/rezbovye-fitingi/chugunnye', '/rezbovye-fitingi?fitting[material]=chugunnye'] as $url) {
            $this->get($url)->assertOk()
                ->assertViewHas('brands', fn ($brands) => $brands->pluck('id')->all() === [$ironBrand->id]);
        }
    }
}

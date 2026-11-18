<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2bCharacteristicFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_is_narrowed_by_diameter_steel_and_attributes(): void
    {
        $parent = Category::create(['name' => 'Дымоходы фильтр', 'slug' => 'char-chimneys', 'is_active' => true]);
        $mono = Category::create(['name' => 'Отводы фильтр', 'slug' => 'char-elbows', 'parent_id' => $parent->id, 'is_active' => true]);
        // The same attribute twice (another category), one filter in the catalog.
        $angle = Attribute::create(['category_id' => $mono->id, 'name' => 'Угол', 'type' => 'select', 'in_filter' => true]);
        $angleCopy = Attribute::create(['category_id' => $parent->id, 'name' => 'Угол ', 'type' => 'select', 'in_filter' => true]);
        $deg45 = AttributeOption::create(['attribute_id' => $angle->id, 'name' => '45°']);
        $deg87 = AttributeOption::create(['attribute_id' => $angle->id, 'name' => '87°']);
        $deg45copy = AttributeOption::create(['attribute_id' => $angleCopy->id, 'name' => '45°']);
        $card = function (string $name, string $slug, ?AttributeOption $option, ?Category $category = null) use ($mono): Product {
            $product = Product::create(['category_id' => ($category ?? $mono)->id, 'name' => $name, 'slug' => $slug, 'price' => 30, 'is_active' => true, 'is_archived' => false]);
            if ($option) {
                ProductAttributeValue::create(['product_id' => $product->id, 'attribute_id' => $option->attribute_id, 'option_id' => $option->id]);
            }

            return $product;
        };
        $a = $card('Отвод Фильтрик Моно ОМ-Р 45°, 430-0,5, Ø 115', 'char-a', $deg45);
        $b = $card('Отвод Фильтрик Моно ОМ-Р 87°, 430-0,8, Ø 115', 'char-b', $deg87);
        $c = $card('Отвод Фильтрик Моно ОМ-Р 45°, 304-0,8, Ø 150', 'char-c', $deg45copy, $parent);
        $d = $card('Отвод Фильтрик Термо ОТ-Р 45°, 430-0,8/430, Ø 150/210', 'char-d', null);
        $manager = User::factory()->create(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true]);
        $page = fn (array $query) => $this->actingAs($manager)->get(route('b2b.catalog', ['workspace' => 1, 'mode' => 'reference', 'category' => $parent->id] + $query));

        // The brand has its own filter; card attributes of thickness and angle are taken in by the facets of the names.
        $maker = Attribute::create(['category_id' => $mono->id, 'name' => 'Производитель', 'type' => 'select', 'in_filter' => true]);
        $thicknessAttribute = Attribute::create(['category_id' => $mono->id, 'name' => 'Толщина металла,  ', 'suffix' => 'мм', 'type' => 'select', 'in_filter' => true]);
        $black = $card('Колено Фильтрик чёрное 45°, Ø 150', 'char-e', null);
        foreach ([[$maker, ['Фильтрик', 'Другой']], [$thicknessAttribute, ['0,8', '2']]] as [$attribute, $names]) {
            foreach ([$a, $black] as $index => $product) {
                $option = AttributeOption::create(['attribute_id' => $attribute->id, 'name' => $names[$index]]);
                ProductAttributeValue::create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'option_id' => $option->id]);
            }
        }

        $page([])->assertOk()->assertSee('Характеристики')->assertSee('Диаметр моно, мм')->assertSee('AISI 304')->assertDontSee('Производитель')
            ->assertViewHas('characteristicPanel', function (array $panel): bool {
                $labels = collect($panel)->pluck('label');
                $options = fn (string $label) => collect(collect($panel)->firstWhere('label', $label)['options'] ?? [])->pluck('count', 'label')->all();

                // The name of a card wins over its attribute (0,5 of «430-0,5»); without it the attribute counts (2 mm).
                return $labels->filter(fn ($label) => $label === 'Угол')->count() === 1 && $labels->filter(fn ($label) => str_starts_with($label, 'Толщина'))->count() === 1
                    && $options('Угол') === ['45°' => 4, '87°' => 1] && $options('Толщина металла, мм') === ['0,5' => 1, '0,8' => 3, '2,0' => 1];
            });

        $ids = fn ($response) => $response->viewData('products')->getCollection()->pluck('id')->sort()->values()->all();
        $this->assertSame([$a->id, $b->id], $ids($page(['dm' => '115'])));
        $this->assertSame([$black->id], $ids($page(['thick' => '2,0'])));
        $this->assertSame([$c->id], $ids($page(['steel' => '304'])));
        $this->assertSame([$d->id], $ids($page(['dp' => '150/210'])));
        // Thickness and angle from the names; the thickness of a sandwich is the inner tube.
        $this->assertSame([$b->id, $c->id, $d->id], $ids($page(['thick' => '0,8'])));
        $this->assertSame([$b->id], $ids($page(['angle' => '87'])));
        $this->assertSame([$a->id], $ids($page(['thick' => '0,5', 'angle' => '45'])));
        // Options of the same name in another category's attribute count as one option.
        $this->assertSame([$a->id, $c->id], $ids($page(['attr' => [$angle->id => [$deg45->id]]])));
        $this->assertSame([$a->id, $b->id, $c->id], $ids($page(['attr' => [$angle->id => [$deg45->id, $deg87->id]]])));
        $this->assertSame([$a->id], $ids($page(['attr' => [$angle->id => [$deg45->id]], 'dm' => '115'])));
        // A selected filter still offers its other values.
        $page(['attr' => [$angle->id => [$deg45->id]]])->assertSee('87°')->assertSee('Сбросить характеристики');
        // A value that is not a diameter or a steel grade is ignored.
        $page(['dm' => '<b>115</b>'])->assertOk()->assertViewHas('characteristicSelection', fn (array $selected) => $selected['names'] === []);
    }

    public function test_without_a_category_no_characteristics_are_offered(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true]);
        $this->actingAs($manager)->get(route('b2b.catalog', ['workspace' => 1, 'mode' => 'reference']))->assertOk()
            ->assertViewHas('characteristicPanel', [])->assertDontSee('b2b-characteristics', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\CategoryNavigationGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeatingAccessoriesNavigationGroupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_large_section_shows_its_subcategories_in_the_configured_groups(): void
    {
        $parent = Category::query()->updateOrCreate(['slug' => 'truby-i-fitingi'], [
            'parent_id' => 0,
            'name' => 'Трубы и фитинги',
            'is_active' => true,
        ]);

        $this->categoryWithProduct($parent->id, 'Полипропиленовые трубы', 'polipropilenovye-truby');
        $this->categoryWithProduct($parent->id, 'Пресс-фитинги', 'press-fitingi');
        $this->categoryWithProduct($parent->id, 'Внутренняя канализация', 'vnutrennyaya-kanalizatsiya');
        $this->categoryWithProduct($parent->id, 'Новая категория без группы', 'novaya-kategoriya');

        $this->get('/'.$parent->slug)
            ->assertOk()
            ->assertSeeInOrder(['Выберите раздел', 'Трубы', 'Полипропиленовые трубы', 'Фитинги', 'Пресс-фитинги', 'Канализация', 'Внутренняя канализация', 'Ещё', 'Новая категория без группы'])
            ->assertDontSee('Изоляция и крепление')
            ->assertSee('id="accessories-nav-search"', false)
            ->assertSee('data-accessories-group', false)
            ->assertSee('href="/press-fitingi"', false);
    }

    public function test_subcategories_no_group_names_land_in_one_more_group(): void
    {
        $children = collect(['press-fitingi', 'novaya-kategoriya', 'polipropilenovye-truby'])
            ->map(fn (string $slug) => new Category(['slug' => $slug, 'name' => $slug]));

        $groups = CategoryNavigationGroups::for('truby-i-fitingi', $children);

        $this->assertSame(['Трубы', 'Фитинги', 'Ещё'], $groups->pluck('title')->all());
        $this->assertSame(['novaya-kategoriya'], $groups->last()['items']->pluck('slug')->all());
        $this->assertTrue(CategoryNavigationGroups::for('unconfigured-category', $children)->isEmpty());
    }

    public function test_radiators_and_installation_parts_have_separate_navigation_groups(): void
    {
        $parent = Category::query()->updateOrCreate(['slug' => 'radiatory'], ['parent_id' => 0, 'name' => 'Радиаторы', 'is_active' => true]);
        $this->categoryWithProduct($parent->id, 'Стальные радиаторы', 'stalnye-radiatory');
        $this->categoryWithProduct($parent->id, 'Биметаллические радиаторы', 'bimetallicheskie-radiatory');
        $this->categoryWithProduct($parent->id, 'Радиаторная арматура', 'radiatornaya-armatura');
        Category::create(['parent_id' => $parent->id, 'name' => 'Пустая категория', 'slug' => 'empty-radiators', 'is_active' => true]);
        $response = $this->get('/radiatory')->assertOk();
        $this->assertSame(['Радиаторы отопления', 'Конвекторы и арматура'], $response->viewData('catalogNavigationGroups')->pluck('title')->all());
        $this->assertSame(['radiatornaya-armatura'], $response->viewData('catalogNavigationGroups')->last()['items']->pluck('slug')->all());
        $response->assertSee('data-accessories-group', false)->assertSee('href="/stalnye-radiatory"', false)->assertDontSee('Пустая категория');
    }

    private function categoryWithProduct(int $parentId, string $name, string $slug): void
    {
        $category = Category::query()->updateOrCreate(['slug' => $slug], [
            'parent_id' => $parentId,
            'name' => $name,
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Товар '.$name,
            'slug' => 'test-'.$slug,
            'sku' => 'TEST-'.mb_strtoupper($slug),
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeatingAccessoriesNavigationGroupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_groups_heating_accessories_and_links_independent_gas_categories(): void
    {
        $parent = Category::query()->updateOrCreate(['slug' => 'komplektuyushhie-dlya-otopleniya'], [
            'parent_id' => 0,
            'name' => 'Комплектующие для отопления',
            'is_active' => true,
        ]);

        $this->categoryWithProduct($parent->id, 'Газовые шланги', 'gazovye-shlangi');
        $this->categoryWithProduct(0, 'Счётчики газа', 'schetchiki-gaza');
        $this->categoryWithProduct($parent->id, 'Гидравлические разделители и коллекторы', 'gidravlicheskie-razdeliteli-i-kollektory');
        $this->categoryWithProduct($parent->id, 'Автоматика и терморегуляторы', 'regulyatory');
        $this->categoryWithProduct($parent->id, 'Мембранные баки', 'membrannye-baki');
        $this->categoryWithProduct($parent->id, 'Комплекты подключения', 'komplekty-podklyucheniya');
        $this->categoryWithProduct($parent->id, 'Электрические ТЭНы', 'elektricheskie-teny-dlya-otopleniya');

        $this->get('/'.$parent->slug)
            ->assertOk()
            ->assertSee('Газовое оборудование и безопасность')
            ->assertSee('Обвязка котельной и арматура')
            ->assertSee('Автоматика, питание и контроль')
            ->assertSee('Баки, теплоносители и фильтрация')
            ->assertSee('Монтаж и подключение')
            ->assertSee('Нагрев и солнечные системы')
            ->assertSee('Быстрый выбор комплектующих')
            ->assertSee('id="accessories-nav-search"', false)
            ->assertSee('data-accessories-group', false)
            ->assertSee('data-accessories-item', false)
            ->assertSee('href="/schetchiki-gaza"', false)
            ->assertSee('href="/gidravlicheskie-razdeliteli-i-kollektory"', false);
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

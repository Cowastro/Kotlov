<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FireboxCatalogFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_combines_glass_and_door_filters_before_pagination(): void
    {
        $category = Category::updateOrCreate(['slug' => 'topki'], ['name' => 'Каминные топки', 'parent_id' => 0, 'is_active' => true]);
        $create = fn ($slug, $name, $specs) => Product::create([
            'category_id' => $category->id, 'slug' => $slug, 'sku' => $slug, 'name' => $name, 'specs' => $specs,
            'price' => 100, 'is_active' => true, 'is_archived' => false, 'in_stock' => true,
        ]);
        $create('filter-tunnel-fixture', 'Тестовая туннельная топка', [
            ['key' => 'Главная', 'value' => 'Прямое'],
            ['key' => 'Открывание дверцы', 'value' => 'Вверх'],
        ]);
        $create('filter-corner-fixture', 'Тестовая угловая топка', [
            ['key' => 'Открывание дверцы', 'value' => 'Вбок'],
        ]);
        $create('filter-straight-fixture', 'Тестовая плоская топка', [
            ['key' => 'Форма стекла', 'value' => 'Прямое'],
            ['key' => 'Тип открывания', 'value' => 'Распашное'],
        ]);
        $create('filter-ambiguous-fixture', 'Тестовая топка без формы', [
            ['key' => 'Вид топки', 'value' => 'Двухсторонняя'],
            ['key' => 'Главная', 'value' => 'Прямое'],
        ]);

        $this->get('https://kotlov.by/topki?firebox_glass=tunnel&firebox_door=lift')
            ->assertOk()
            ->assertSee('content="noindex, follow"', false)
            ->assertSee('href="https://kotlov.by/topki"', false)
            ->assertSeeText('Тестовая туннельная топка')
            ->assertSeeText('Стекло: Туннельное')
            ->assertSeeText('Дверца: Вверх')
            ->assertDontSeeText('Тестовая угловая топка')
            ->assertDontSeeText('Тестовая плоская топка');

        $this->get('https://kotlov.by/topki?firebox_glass=straight')
            ->assertOk()
            ->assertSeeText('Тестовая плоская топка')
            ->assertDontSeeText('Тестовая туннельная топка')
            ->assertDontSeeText('Тестовая топка без формы');

        $this->get('https://kotlov.by/topki/filter-corner-fixture')
            ->assertOk()
            ->assertSeeText('Стекло: Угловое')
            ->assertSeeText('Дверца: Вбок');
    }
}

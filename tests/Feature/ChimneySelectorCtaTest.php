<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChimneySelectorCtaTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_chimney_section_level_shows_the_calculator_with_the_chosen_diameter(): void
    {
        $root = Category::updateOrCreate(['slug' => 'dymohody'], ['name' => 'Дымоходы', 'parent_id' => 0, 'is_active' => true]);
        $mono = Category::updateOrCreate(['slug' => 'dymohody-mono'], ['name' => 'Дымоходы Моно', 'parent_id' => $root->id, 'is_active' => true]);
        $pipes = Category::updateOrCreate(['slug' => 'truby-mono'], ['name' => 'Трубы Моно', 'parent_id' => $mono->id, 'is_active' => true]);
        $boilers = Category::updateOrCreate(['slug' => 'gazovye'], ['name' => 'Газовые котлы', 'parent_id' => 0, 'is_active' => true]);
        $pipe = Product::create(['name' => 'Труба моно 430 0.8 Ø120 L1000', 'slug' => 'truba-mono-120', 'sku' => 'CTA-PIPE', 'category_id' => $pipes->id, 'price' => 50, 'is_active' => true, 'is_archived' => false]);
        Product::create(['name' => 'Котел газовый тест', 'slug' => 'kotel-test', 'sku' => 'CTA-BOILER', 'category_id' => $boilers->id, 'price' => 900, 'is_active' => true, 'is_archived' => false]);

        $this->get('/truby-mono?chimney_diameter=120')
            ->assertOk()
            ->assertSee('Собрать дымоход в калькуляторе')
            ->assertSee(route('chimney-selector', ['chimney_diameter' => '120']), false);
        $this->get('/dymohody')->assertOk()->assertSee('Собрать дымоход в калькуляторе');
        $this->get('/gazovye')->assertOk()->assertDontSee('Собрать дымоход в калькуляторе');
    }
}

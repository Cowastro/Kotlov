<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaunaRoomVolumeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_keeps_only_ranges_containing_the_volume_and_respects_existing_filters(): void
    {
        Category::updateOrCreate(['slug' => 'pechi-dlya-bani'], ['name' => 'Аксессуары для парной', 'parent_id' => 0, 'is_active' => true]);
        $wood = Category::updateOrCreate(['slug' => 'drovianye-peci-bannye'], ['name' => 'Дровяные печи', 'parent_id' => 0, 'is_active' => true]);

        $makeStove = fn ($slug, $name, $volume, $inStock = true) => Product::create([
            'name' => $name, 'slug' => $slug, 'sku' => $slug, 'category_id' => $wood->id,
            'is_active' => true, 'is_archived' => false, 'in_stock' => $inStock, 'price' => 500,
            'specs' => [['key' => 'Объём парной (м³)', 'value' => $volume]],
        ]);
        $makeStove('sauna-fixture-match', 'Точная банная печь', '8 м³–12,5 м³');
        $makeStove('sauna-fixture-large', 'Большая банная печь', '14–24 м³');
        $makeStove('sauna-fixture-max', 'Печь только с максимумом', 'до 18 м³');
        $makeStove('sauna-fixture-out', 'Печь без наличия', '8–20 м³', false);

        $this->get('https://kotlov.by/pechi-dlya-bani')
            ->assertOk()
            ->assertSee('action="https://kotlov.by/drovianye-peci-bannye#catalog-products"', false);
        $this->get('https://kotlov.by/pechi-dlya-bani?sauna_volume=12%2C5&in_stock=1')
            ->assertRedirect('https://kotlov.by/drovianye-peci-bannye?sauna_volume=12%2C5&in_stock=1#catalog-products');

        $response = $this->get('https://kotlov.by/drovianye-peci-bannye?sauna_volume=12%2C5&in_stock=1');
        $response->assertOk()
            ->assertSee('data-sauna-volume-results="1"', false)
            ->assertSee('content="noindex, follow"', false)
            ->assertSeeText('Точная банная печь')
            ->assertSeeText('8–12,5 м³')
            ->assertDontSeeText('Большая банная печь')
            ->assertDontSeeText('Печь только с максимумом')
            ->assertDontSeeText('Печь без наличия');

        $this->get('https://kotlov.by/drovianye-peci-bannye')
            ->assertOk()
            ->assertSeeText('До 18 м³')
            ->assertSeeText('Большая банная печь');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishTenkoThermokraftCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_missing_tenko_cards_and_consolidates_the_brand(): void
    {
        $boilers = Category::query()->firstOrCreate(['slug' => 'elektricheskie'], ['name' => 'Электрические котлы', 'parent_id' => 0, 'is_active' => true]);
        $heaters = Category::query()->firstOrCreate(['slug' => 'elektricheskie-teny-dlya-otopleniya'], ['name' => 'ТЭНы', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->updateOrCreate(['slug' => 'tenko'], ['name' => 'Tenko', 'is_active' => true]);
        $duplicate = Brand::query()->create(['name' => 'TENKO', 'slug' => 'tenko-2', 'is_active' => true]);
        $legacy = Product::query()->create([
            'category_id' => $boilers->id, 'brand_id' => $duplicate->id, 'is_active' => true, 'price' => 0,
            'name' => 'Электрический котел Tenko ЭКОНОМ (КЕ) 18 кВт 380В', 'slug' => 'legacy-tenko-ekonom-18',
        ]);
        $broken = Product::query()->create([
            'category_id' => $heaters->id, 'brand_id' => $brand->id, 'is_active' => true, 'price' => 100,
            'name' => 'ТЭН трубчатый 9000 Вт/220В крепление гайка латунная 1,5" L=420мм', 'slug' => 'broken-ten',
            'images' => ['https://tenko.ua/ru/catalog/good/img/search.png'],
        ]);
        Product::query()->create([
            'category_id' => $heaters->id, 'brand_id' => $brand->id, 'is_active' => true, 'price' => 100,
            'name' => 'ТЭН трубчатый 7500 Вт/220В крепление гайка латунная 1,5" L=370мм', 'slug' => 'donor-ten',
            'images' => ['/img/products/tenko/tenko-heater-g114.jpg'],
        ]);
        $before = Product::query()->count();

        $this->artisan('catalog:publish-tenko-thermokraft')
            ->expectsOutputToContain('metric.tenko.planned_items=39')
            ->expectsOutputToContain('metric.tenko.already_present=1')
            ->assertSuccessful();
        $this->assertSame($before, Product::query()->count());

        $this->artisan('catalog:publish-tenko-thermokraft --apply')->assertSuccessful();
        $this->artisan('catalog:publish-tenko-thermokraft --apply')->assertSuccessful();

        $this->assertSame($before + 38, Product::query()->count());
        $this->assertSame($brand->id, $legacy->fresh()->brand_id);
        $this->assertFalse($duplicate->fresh()->is_active);
        $this->assertSame(['/img/products/tenko/tenko-heater-g114.jpg'], $broken->fresh()->images);
        $this->assertSame('img/brands/tenko.png', $brand->fresh()->logo);

        $digital = Product::query()->where('name', 'Электрический котел Tenko Standart Digital 6 кВт 380В')->sole();
        $this->assertSame('1497.00', (string) $digital->price);
        $this->assertSame($boilers->id, $digital->category_id);
        $this->assertCount(3, $digital->images);
        $this->assertFalse($digital->requires_price_confirmation);
        $this->assertStringContainsString('Номинальная коммутируемая мощность', json_encode($digital->specs, JSON_UNESCAPED_UNICODE));
        $this->assertSame(
            (new \App\Console\Commands\SyncThermokraftCommand)->modelKey('Котел Tenko Standart Digital 6_380 ', 'TENKO'),
            (new \App\Console\Commands\SyncThermokraftCommand)->modelKey($digital->name, 'Tenko'),
        );
    }
}

<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopyCardContentCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_another_size_gets_the_photos_texts_and_specs_with_its_own_diameter(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'krepleniya-dymohod'], ['name' => 'Крепления', 'is_active' => true]);
        $brand = Brand::query()->where('name', 'Теплов и Сухов')->first() ?? Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-i-suxov', 'is_active' => true]);
        $from = Product::query()->create(['category_id' => $category->id, 'name' => 'Кольцо ф110', 'slug' => 'ring-110', 'price' => 5,
            'images' => ['img/products/ring-1.jpg', 'img/products/ring-2.jpg'], 'content' => '<p>Кольцо дымохода ф110 для газоуплотнения.</p>',
            'specs' => [['key' => 'Диаметр, мм', 'value' => '110'], ['key' => 'Артикул', 'value' => 'TS.KMP..0110.75017'], ['key' => 'Страна-производитель', 'value' => 'Россия']]]);
        $to = Product::query()->create(['category_id' => $category->id, 'name' => 'Кольцо ф120', 'slug' => 'ring-120', 'price' => 0,
            'specs' => [['key' => 'Диаметр', 'value' => '120 мм']]]);

        $this->artisan('catalog:copy-card-content', ['from' => $from->id, 'to' => $to->id, '--replace' => ['ф110::ф120'],
            '--spec' => ['Диаметр, мм: 120', 'Артикул: TS.KMP.UKL.0120.75018'], '--brand' => 'Теплов и Сухов'])
            ->expectsOutputToContain('audit.copy_card.plan='.$from->id.':'.$to->id.':images2:specs3')->assertSuccessful();
        $this->assertNull($to->fresh()->images);

        $this->artisan('catalog:copy-card-content', ['from' => $from->id, 'to' => $to->id, '--replace' => ['ф110::ф120'],
            '--spec' => ['Диаметр, мм: 120', 'Артикул: TS.KMP.UKL.0120.75018'], '--brand' => 'Теплов и Сухов', '--apply' => true])->assertSuccessful();

        $to->refresh();
        $this->assertSame('Кольцо ф120', $to->name);
        $this->assertSame(['img/products/ring-1.jpg', 'img/products/ring-2.jpg'], $to->images);
        $this->assertStringContainsString('ф120', $to->content);
        $this->assertSame($brand->id, $to->brand_id);
        $specs = collect($to->specs)->pluck('value', 'key');
        $this->assertSame('120', $specs['Диаметр, мм']);
        $this->assertSame('TS.KMP.UKL.0120.75018', $specs['Артикул']);
        $this->assertSame('Россия', $specs['Страна-производитель']);
    }

    public function test_an_unknown_brand_is_not_created(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'krepleniya-dymohod'], ['name' => 'Крепления', 'is_active' => true]);
        $from = Product::query()->create(['category_id' => $category->id, 'name' => 'A', 'slug' => 'a', 'price' => 1]);
        $to = Product::query()->create(['category_id' => $category->id, 'name' => 'B', 'slug' => 'b', 'price' => 1]);

        $this->artisan('catalog:copy-card-content', ['from' => $from->id, 'to' => $to->id, '--brand' => 'Нет такого', '--apply' => true])
            ->expectsOutputToContain('audit.copy_card.skipped='.$to->id.':no_brand')->assertFailed();
    }
}

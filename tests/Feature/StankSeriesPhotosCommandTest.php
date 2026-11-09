<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StankSeriesPhotosCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_hidden_cards_take_the_series_render_or_our_card_of_the_series(): void
    {
        Supplier::query()->create(['name' => 'S-TANK', 'code' => 'stank', 'is_active' => true]);
        $brand = Brand::query()->firstOrCreate(['name' => 'S-TANK'], ['slug' => 's-tank-test', 'is_active' => true]);
        $category = Category::query()->create(['name' => 'Баки', 'slug' => 'baki-stank-test', 'parent_id' => 0, 'is_active' => true]);
        $card = fn (string $name, array $images, ?string $via) => Product::query()->forceCreate(['name' => $name, 'slug' => Str::slug($name),
            'category_id' => $category->id, 'brand_id' => $brand->id, 'price' => 100, 'is_active' => $via === null, 'images' => $images, 'created_via' => $via]);
        $etro = $card('Бак электронагрева ГВС S-TANK AT ETRO 300', [], Product::CREATED_VIA_AWAITING_PHOTOS);
        $card('Бак косвенного нагрева S-TANK BER2-200', ['img/products/stank/ber2-200.webp'], null);
        $ber = $card('Бак косвенного нагрева S-TANK BER2-300', [], Product::CREATED_VIA_AWAITING_PHOTOS);

        $this->artisan('supplier:stank-series-photos', ['--apply' => true])
            ->expectsOutputToContain('metric.stank_series_photos.site=1')
            ->expectsOutputToContain('metric.stank_series_photos.own=1')
            ->expectsOutputToContain('metric.stank_series_photos.published=2')
            ->assertSuccessful();

        $this->assertSame(['img/products/stank/series/stank-atetro-1.webp'], $etro->fresh()->images);
        $this->assertTrue($etro->fresh()->is_active);
        $this->assertSame(['img/products/stank/ber2-200.webp'], $ber->fresh()->images);
        $this->assertNull($ber->fresh()->created_via);
    }
}

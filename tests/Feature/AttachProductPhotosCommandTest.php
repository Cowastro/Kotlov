<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AttachProductPhotosCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_several_photos_replace_a_wrong_gallery(): void
    {
        $jpeg = function (int $shade): string {
            $image = imagecreatetruecolor(768, 1024);
            imagefilledrectangle($image, 100, 100, 668, 924, imagecolorallocate($image, $shade, $shade, $shade));
            imagefilledrectangle($image, 0, 0, 768, 60, imagecolorallocate($image, 200, 90, 40));
            ob_start();
            imagejpeg($image);

            return (string) ob_get_clean();
        };
        Http::fake([
            'atem.ua/i/photos/front.jpg' => Http::response($jpeg(40), 200, ['Content-Type' => 'image/jpeg']),
            'atem.ua/i/photos/side.jpg' => Http::response($jpeg(90), 200, ['Content-Type' => 'image/jpeg']),
        ]);
        $category = Category::query()->create(['name' => 'Твердотопливные', 'slug' => 'tverdotoplivnye', 'parent_id' => 0, 'is_active' => true]);
        $card = Product::query()->create(['name' => 'Котел Житомир-14М ТРИ ДВЕРИ', 'slug' => 'kotel-zitomir-14m', 'sku' => 'KOTLOV-004904',
            'category_id' => $category->id, 'price' => 3497, 'is_active' => true, 'images' => ['img/products/gas-boiler.jpg']]);

        $this->artisan('catalog:attach-photos', ['--url' => [$card->id.':https://atem.ua/i/photos/front.jpg,https://atem.ua/i/photos/side.jpg'], '--replace' => true, '--apply' => true])
            ->expectsOutputToContain('metric.attach_photos.downloaded=2')
            ->assertSuccessful();

        $images = (array) $card->fresh()->images;
        $this->assertCount(2, $images);
        $this->assertNotContains('img/products/gas-boiler.jpg', $images);
        foreach ($images as $path) {
            @unlink(public_path($path));
        }
    }
}

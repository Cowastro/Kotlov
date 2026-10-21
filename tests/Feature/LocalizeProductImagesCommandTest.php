<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocalizeProductImagesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_downloads_remote_gallery_images_and_preserves_local_entries(): void
    {
        $directory = public_path('img/products/localized/viessmann-test');
        File::deleteDirectory($directory);

        try {
            Http::fake([
                'https://images.example.test/*' => Http::response(
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
                    200,
                    ['Content-Type' => 'image/png']
                ),
            ]);
            $category = Category::query()->create(['name' => 'Газовые котлы', 'slug' => 'gas-localize-test']);
            $brand = Brand::query()->create(['name' => 'Viessmann Test', 'slug' => 'viessmann-test']);
            $product = Product::query()->create([
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'name' => 'Viessmann Test Boiler',
                'slug' => 'viessmann-test-boiler',
                'sku' => 'VIESSMANN-TEST',
                'price' => 100,
                'is_active' => true,
                'is_archived' => false,
                'requires_price_confirmation' => false,
                'images' => [
                    'https://images.example.test/boiler.png',
                    'img/products/existing/local.jpg',
                ],
            ]);

            $this->artisan('catalog:localize-product-images', ['--brand' => 'Viessmann Test'])
                ->expectsOutputToContain('metric.localize_images.cards_with_remote=1')
                ->expectsOutputToContain('metric.localize_images.cards_updated=0')
                ->assertSuccessful();
            $this->assertSame('https://images.example.test/boiler.png', $product->fresh()->images[0]);

            $this->artisan('catalog:localize-product-images', [
                '--brand' => 'Viessmann Test',
                '--apply' => true,
            ])
                ->expectsOutputToContain('metric.localize_images.cards_updated=1')
                ->expectsOutputToContain('metric.localize_images.images_saved=1')
                ->assertSuccessful();

            $images = $product->fresh()->images;
            $this->assertStringStartsWith('img/products/localized/viessmann-test/', $images[0]);
            $this->assertFileExists(public_path($images[0]));
            $this->assertSame('img/products/existing/local.jpg', $images[1]);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}

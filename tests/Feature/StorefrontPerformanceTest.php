<?php

namespace Tests\Feature;

use App\Support\StorefrontImage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontPerformanceTest extends TestCase
{
    public function test_legacy_icon_endpoint_needs_no_database_even_on_a_city_host(): void
    {
        $queries = [];
        DB::listen(function ($event) use (&$queries): void {
            $queries[] = $event->sql;
        });
        $this->get('https://lida.kotlov.by/catalog-icons/heater')
            ->assertOk()->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8');
        $this->assertSame([], $queries);
    }

    public function test_thumbnail_reuses_optimized_local_image_without_replacing_external_or_missing_images(): void
    {
        $optimized = StorefrontImage::categoryThumbnail('https://lida.kotlov.by/img/popular/boiler_img.jpg');
        $this->assertStringContainsString('/img/popular/optimized/boiler_img.webp', $optimized);
        $foreign = 'https://images.example.com/img/popular/boiler_img.jpg';
        $this->assertSame($foreign, StorefrontImage::categoryThumbnail($foreign));
        $missing = '/img/popular/no-such-photo.jpg';
        $this->assertSame($missing, StorefrontImage::categoryThumbnail($missing));
        $original = '/img/products/product-photo.jpg';
        $this->assertSame($original, StorefrontImage::categoryThumbnail($original));
    }
}

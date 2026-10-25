<?php

namespace Tests\Feature;

use App\Console\Commands\Concerns\ScrapesAqualiderCard;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AqualiderImageFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_card_without_photos_takes_the_only_small_aqualider_photo(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'tsirkulyatsionnyie'], ['name' => 'Циркуляционные насосы', 'parent_id' => 0, 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 900, 'name' => 'IMP PUMPS NMT SAN SMART C 25/40', 'slug' => 'nmt-san-25-40']);
        Http::fake([
            'aqualider.by/static/small.png' => Http::response($this->png(276, 276), 200, ['Content-Type' => 'image/png']),
            'aqualider.by/static/icon.png' => Http::response($this->png(120, 120), 200, ['Content-Type' => 'image/png']),
        ]);
        $downloader = new class {
            use ScrapesAqualiderCard;

            public function download(int $id, array $urls): int
            {
                return $this->downloadCardImages($id, $urls, 'img/products/test-aq');
            }
        };

        $this->assertSame(1, $downloader->download($card->id, ['https://aqualider.by/static/icon.png', 'https://aqualider.by/static/small.png']));
        $this->assertSame(['img/products/test-aq/aq-'.$card->id.'-1.png'], $card->fresh()->images);
        File::deleteDirectory(public_path('img/products/test-aq'));
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}

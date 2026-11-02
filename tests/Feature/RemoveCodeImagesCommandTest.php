<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoveCodeImagesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_qr_code_is_told_from_a_photo(): void
    {
        $this->assertTrue(ProductSourceEnricher::looksLikeCodeImage($this->qrCode()));
        $this->assertFalse(ProductSourceEnricher::looksLikeCodeImage($this->photo()));
        $this->assertFalse(ProductSourceEnricher::looksLikeCodeImage($this->grate()));
    }

    public function test_a_qr_code_is_taken_off_and_a_card_left_without_photos_is_hidden_again(): void
    {
        $dir = public_path('img/products/source-enrichment');
        is_dir($dir) || mkdir($dir, 0755, true);
        file_put_contents("{$dir}/test-qr-1.png", $this->qrCode());
        file_put_contents("{$dir}/test-photo-1.png", $this->photo());
        $category = Category::query()->create(['name' => 'Котлы', 'slug' => 'kotly-qr-test', 'parent_id' => 0, 'is_active' => true]);
        $card = fn (string $slug, array $images) => Product::query()->create(['name' => $slug, 'slug' => $slug, 'category_id' => $category->id,
            'price' => 1, 'is_active' => true, 'images' => $images]);
        $onlyQr = $card('only-qr', ['img/products/source-enrichment/test-qr-1.png']);
        $mixed = $card('mixed', ['img/products/source-enrichment/test-photo-1.png', 'img/products/source-enrichment/test-qr-1.png']);
        $supplier = Supplier::query()->create(['name' => 'ТеплоТоп', 'code' => 'teplotop-qr-test']);
        $offer = SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'product_id' => $onlyQr->id, 'supplier_article' => 'a1',
            'supplier_name' => 'only-qr', 'raw' => ['photo_searched_at' => '2026-11-01 10:00:00', 'deal_by_id' => '5']]);

        try {
            $this->artisan('catalog:remove-code-images', ['--apply' => true])
                ->expectsOutputToContain('metric.code_images.cards=2')
                ->expectsOutputToContain('metric.code_images.hidden=1')
                ->assertSuccessful();
        } finally {
            @unlink("{$dir}/test-qr-1.png");
            @unlink("{$dir}/test-photo-1.png");
        }

        $onlyQr->refresh();
        $this->assertSame([[], false, Product::CREATED_VIA_AWAITING_PHOTOS], [$onlyQr->images, $onlyQr->is_active, $onlyQr->created_via]);
        $this->assertSame(['img/products/source-enrichment/test-photo-1.png'], $mixed->fresh()->images);
        $this->assertNull($offer->fresh()->raw['photo_searched_at']);
        $this->assertSame('5', $offer->fresh()->raw['deal_by_id']);
    }

    private function qrCode(): string
    {
        mt_srand(7);
        $image = imagecreatetruecolor(740, 740);
        $white = imagecolorallocate($image, 248, 249, 250);
        $black = imagecolorallocate($image, 26, 26, 26);
        imagefill($image, 0, 0, $white);
        for ($x = 1; $x < 36; $x++) {
            for ($y = 1; $y < 36; $y++) {
                if (mt_rand(0, 1)) {
                    imagefilledrectangle($image, $x * 20, $y * 20, $x * 20 + 19, $y * 20 + 19, $black);
                }
            }
        }
        // The three finder squares: a 7-module ring, a light ring, a 3-module core.
        foreach ([[1, 1], [29, 1], [1, 29]] as [$mx, $my]) {
            $square = fn (int $from, int $size, int $color) => imagefilledrectangle($image, ($mx + $from) * 20, ($my + $from) * 20, ($mx + $from + $size) * 20 - 1, ($my + $from + $size) * 20 - 1, $color);
            $square(-1, 9, $white);
            $square(0, 7, $black);
            $square(1, 5, $white);
            $square(2, 3, $black);
        }

        return $this->png($image);
    }

    /** A grey item with shading on a white background. */
    private function photo(): string
    {
        $image = imagecreatetruecolor(600, 600);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        for ($y = 100; $y < 500; $y++) {
            $shade = 60 + (int) (($y - 100) / 400 * 120);
            imagefilledrectangle($image, 150, $y, 450, $y, imagecolorallocate($image, $shade, $shade, $shade + 10));
        }

        return $this->png($image);
    }

    /** A black grate with many slots on white: the colours of a code without its finders. */
    private function grate(): string
    {
        $image = imagecreatetruecolor(600, 600);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        $black = imagecolorallocate($image, 20, 20, 20);
        imagefilledellipse($image, 300, 300, 560, 560, $black);
        $white = imagecolorallocate($image, 255, 255, 255);
        for ($y = 80; $y < 520; $y += 30) {
            for ($x = 80; $x < 520; $x += 50) {
                imagefilledrectangle($image, $x, $y, $x + 30, $y + 12, $white);
            }
        }

        return $this->png($image);
    }

    private function png(\GdImage $image): string
    {
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}

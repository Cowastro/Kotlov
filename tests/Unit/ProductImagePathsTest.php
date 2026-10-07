<?php

namespace Tests\Unit;

use App\Models\Product;
use PHPUnit\Framework\TestCase;

class ProductImagePathsTest extends TestCase
{
    public function test_it_normalizes_a_legacy_json_scalar_to_a_gallery_array(): void
    {
        $product = new Product;
        $product->setRawAttributes(['images' => json_encode('product/thermex/vetro.jpg')]);

        $this->assertSame(['product/thermex/vetro.jpg'], $product->imagePaths());
        $this->assertSame('product/thermex/vetro.jpg', $product->main_image);
    }

    public function test_it_filters_invalid_gallery_values(): void
    {
        $product = new Product;
        $product->setRawAttributes(['images' => json_encode([
            'img/products/one.jpg',
            null,
            '',
            ['unexpected'],
            'img/products/two.jpg',
        ])]);

        $this->assertSame([
            'img/products/one.jpg',
            'img/products/two.jpg',
        ], $product->imagePaths());
    }
}

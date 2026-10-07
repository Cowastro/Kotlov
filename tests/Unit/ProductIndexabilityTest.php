<?php

namespace Tests\Unit;

use App\Models\Product;
use PHPUnit\Framework\TestCase;

class ProductIndexabilityTest extends TestCase
{
    public function test_orderable_product_is_indexable(): void
    {
        $product = new Product([
            'price' => 100,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);

        $this->assertTrue($product->canBeOrdered());
    }

    public function test_product_without_price_is_not_orderable(): void
    {
        $product = new Product([
            'price' => 0,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);

        $this->assertFalse($product->canBeOrdered());
    }

    public function test_unavailable_product_is_not_orderable(): void
    {
        $product = new Product([
            'price' => 100,
            'is_archived' => false,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_OUT_OF_STOCK,
        ]);

        $this->assertFalse($product->canBeOrdered());
    }
}

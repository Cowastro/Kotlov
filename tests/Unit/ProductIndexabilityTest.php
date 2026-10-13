<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\SupplierProduct;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProductIndexabilityTest extends TestCase
{
    public function test_orderable_product_is_indexable(): void
    {
        $product = new Product([
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
        $product->setRelation('supplierProducts', new Collection([
            new SupplierProduct(['in_stock' => true, 'stock_quantity' => 1]),
        ]));

        $this->assertTrue($product->canBeOrdered());
    }

    public function test_product_without_price_is_not_orderable(): void
    {
        $product = new Product([
            'price' => 0,
            'is_active' => true,
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
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_OUT_OF_STOCK,
        ]);

        $this->assertFalse($product->canBeOrdered());
    }

    public function test_unlinked_product_with_an_unverified_price_is_not_orderable(): void
    {
        $product = new Product([
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $product->setRelation('supplierProducts', new Collection());
        $product->setRelation('integrationProducts', new Collection());

        $this->assertFalse($product->canBeOrdered());
    }

    public function test_unlinked_product_with_a_fresh_market_price_is_orderable(): void
    {
        $product = new Product([
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'market_price_verified_at' => now(),
            'market_price_source_url' => 'https://market.example/product',
        ]);
        $product->setRelation('supplierProducts', new Collection());
        $product->setRelation('integrationProducts', new Collection());

        $this->assertTrue($product->canBeOrdered());
    }
}

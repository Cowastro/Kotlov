<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\SupplierProduct;
use Carbon\CarbonImmutable;
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
            new SupplierProduct([
                'in_stock' => true,
                'stock_quantity' => 1,
                'last_stock_synced_at' => CarbonImmutable::now(),
            ]),
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

    public function test_product_that_needs_availability_confirmation_cannot_enter_checkout(): void
    {
        $product = new Product([
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $product->setRelation('supplierProducts', new Collection);
        $product->setRelation('integrationProducts', new Collection);

        $this->assertTrue($product->canBeOrdered());
        $this->assertFalse($product->canBePurchased());
    }

    public function test_confirmed_supplier_stock_can_enter_checkout(): void
    {
        $product = new Product([
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
        $product->setRelation('supplierProducts', new Collection([
            new SupplierProduct([
                'in_stock' => true,
                'stock_quantity' => 1,
                'last_stock_synced_at' => CarbonImmutable::now(),
            ]),
        ]));

        $this->assertTrue($product->canBePurchased());
    }

    public function test_stale_supplier_in_stock_flag_with_zero_quantity_cannot_enter_checkout(): void
    {
        $product = new Product([
            'price' => 33,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK,
        ]);
        $product->setRelation('supplierProducts', new Collection([
            new SupplierProduct(['in_stock' => true, 'stock_quantity' => 0]),
        ]));
        $product->setRelation('integrationProducts', new Collection);

        $this->assertSame(Product::AVAILABILITY_CHECK, $product->effectiveAvailabilityStatus());
        $this->assertFalse($product->canBePurchased());
    }
}

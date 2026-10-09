<?php

namespace Tests\Unit;

use App\Models\IntegrationProduct;
use PHPUnit\Framework\TestCase;

class IntegrationProductTest extends TestCase
{
    public function test_stock_quantity_is_shown_without_meaningless_decimal_zeroes(): void
    {
        $product = new IntegrationProduct(['stock_quantity' => '7.000']);

        $this->assertSame('7 шт.', $product->formattedStockQuantity());
    }

    public function test_fractional_stock_quantity_keeps_only_significant_digits(): void
    {
        $product = new IntegrationProduct(['stock_quantity' => '1.500']);

        $this->assertSame('1.5 шт.', $product->formattedStockQuantity());
    }
}

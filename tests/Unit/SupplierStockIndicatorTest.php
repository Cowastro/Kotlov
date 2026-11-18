<?php

namespace Tests\Unit;

use App\Services\Suppliers\SupplierStockIndicator;
use PHPUnit\Framework\TestCase;

class SupplierStockIndicatorTest extends TestCase
{
    public function test_stale_numbers_and_missing_quantities_never_become_high_stock(): void
    {
        $this->assertSame('unknown', SupplierStockIndicator::fromOffer(['is_fresh' => false, 'stock_quantity' => 999])['state']);
        $this->assertSame('unknown', SupplierStockIndicator::fromOffer(['is_fresh' => true, 'stock_quantity' => null, 'in_stock' => false])['state']);
        $this->assertSame(0, SupplierStockIndicator::fromOffer(['is_fresh' => true, 'stock_quantity' => null, 'in_stock' => true])['level']);
        $this->assertSame('Есть', SupplierStockIndicator::fromOffer(['is_fresh' => true, 'stock_quantity' => null, 'in_stock' => true])['label']);
    }

    public function test_confirmed_quantities_keep_the_exact_number_and_unit(): void
    {
        foreach ([0 => 0, 1 => 1, 5 => 1, 6 => 2, 20 => 2, 21 => 3] as $quantity => $level) {
            $indicator = SupplierStockIndicator::fromOffer(['is_fresh' => true, 'stock_quantity' => $quantity, 'order_rules' => ['unit' => 'уп.']]);
            $this->assertSame($level, $indicator['level']);
            $this->assertSame($quantity.' уп.', $indicator['label']);
        }
    }
}

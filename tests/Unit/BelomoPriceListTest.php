<?php

namespace Tests\Unit;

use App\Services\PriceList\BelomoPriceList;
use PHPUnit\Framework\TestCase;

class BelomoPriceListTest extends TestCase
{
    public function test_static_price_list_uses_the_high_column_without_vat_recalculation(): void
    {
        $priceList = new BelomoPriceList();
        $rows = $priceList->rows(dirname(__DIR__, 2).'/database/data/supplier-price-lists/belomo-2026-09-15.csv');

        $this->assertCount(77, $rows);
        $this->assertSame('1009.00.00.000-00', $rows[0]['article']);
        $this->assertSame(153.65, $rows[0]['partner_price_byn']);
        $this->assertSame('8349.00.00.000-17', $rows[76]['article']);
        $this->assertSame(823.0, $rows[76]['partner_price_byn']);
        $this->assertSame('834912', $priceList->shortArticle('8349.00.00.000-12'));
    }
}

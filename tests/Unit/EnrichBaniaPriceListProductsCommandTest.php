<?php

namespace Tests\Unit;

use App\Console\Commands\EnrichBaniaPriceListProductsCommand;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class EnrichBaniaPriceListProductsCommandTest extends TestCase
{
    public function test_official_description_can_confirm_details_missing_from_title(): void
    {
        $command = new EnrichBaniaPriceListProductsCommand();
        $method = new ReflectionMethod($command, 'isLikelyTitleMatch');
        $product = (object) [
            'name' => 'Антисептик для внутренних работ PROSEPT SAUNA концентрат 1:10 / 1 л',
            'supplier_name' => '',
        ];
        $officialIdentity = 'PROSEPT SAUNA 1 л. Антисептик на водной основе, концентрат 1:10 для внутренних работ в банях и саунах.';

        self::assertTrue($method->invoke($command, $officialIdentity, $product));
    }
}

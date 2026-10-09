<?php

namespace Tests\Unit;

use App\Models\IntegrationSource;
use PHPUnit\Framework\TestCase;

class IntegrationSourcePricingTest extends TestCase
{
    public function test_exclusive_price_is_converted_to_customer_price_with_tax(): void
    {
        $source = new IntegrationSource([
            'settings' => ['price_tax_mode' => 'exclusive', 'vat_rate' => 20],
        ]);

        $this->assertSame(96.0, $source->priceIncludingTax(80));
        $this->assertSame('без НДС', $source->sourcePriceTaxLabel());
        $this->assertSame('Без НДС → +20%', $source->pricingRuleLabel());
    }

    public function test_inclusive_price_is_not_taxed_twice(): void
    {
        $source = new IntegrationSource([
            'settings' => ['price_tax_mode' => 'inclusive', 'vat_rate' => 20],
        ]);

        $this->assertSame(80.0, $source->priceIncludingTax(80));
        $this->assertSame('с НДС', $source->sourcePriceTaxLabel());
        $this->assertSame('Передаётся с НДС', $source->pricingRuleLabel());
    }
}

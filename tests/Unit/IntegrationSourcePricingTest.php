<?php

namespace Tests\Unit;

use App\Models\IntegrationSource;
use PHPUnit\Framework\TestCase;

class IntegrationSourcePricingTest extends TestCase
{
    public function test_only_classified_sale_price_can_be_published_to_b2b(): void
    {
        $sale = new IntegrationSource(['settings' => ['b2b_price_role' => IntegrationSource::B2B_PRICE_ROLE_SALE]]);
        $purchase = new IntegrationSource(['settings' => ['b2b_price_role' => IntegrationSource::B2B_PRICE_ROLE_PURCHASE]]);
        $unknown = new IntegrationSource(['settings' => []]);

        $this->assertTrue($sale->hasPublishableB2bPrice());
        $this->assertFalse($purchase->hasPublishableB2bPrice());
        $this->assertFalse($unknown->hasPublishableB2bPrice());
        $this->assertSame('Готовая цена продажи B2B', $sale->b2bPriceRoleLabel());
        $this->assertSame('Закупочная цена — нужна продажная цена', $purchase->b2bPriceRoleLabel());
        $this->assertSame('Не классифицирована', $unknown->b2bPriceRoleLabel());
    }

    public function test_exclusive_price_is_converted_to_customer_price_with_tax(): void
    {
        $source = new IntegrationSource([
            'settings' => ['price_tax_mode' => 'exclusive', 'vat_rate' => 20],
        ]);

        $this->assertSame(96.0, $source->priceIncludingTax(80));
        $this->assertSame('без НДС', $source->sourcePriceTaxLabel());
        $this->assertSame('BYN · Без НДС → +20%', $source->pricingRuleLabel());
        $this->assertSame(96.0, $source->normalizePriceToByn(80));
    }

    public function test_inclusive_price_is_not_taxed_twice(): void
    {
        $source = new IntegrationSource([
            'settings' => ['price_tax_mode' => 'inclusive', 'vat_rate' => 20],
        ]);

        $this->assertSame(80.0, $source->priceIncludingTax(80));
        $this->assertSame('с НДС', $source->sourcePriceTaxLabel());
        $this->assertSame('BYN · Передаётся с НДС', $source->pricingRuleLabel());
        $this->assertSame(80.0, $source->normalizePriceToByn(80));
    }

    public function test_foreign_price_is_converted_to_byn_before_tax(): void
    {
        $source = new IntegrationSource([
            'price_currency' => 'EUR',
            'price_currency_rate' => 3.5,
            'settings' => ['price_tax_mode' => 'exclusive', 'vat_rate' => 20],
        ]);

        $this->assertSame(42.0, $source->normalizePriceToByn(10));
        $this->assertSame('EUR × 3.5 · Без НДС → +20%', $source->pricingRuleLabel());
        $this->assertSame([
            'price_currency' => 'EUR',
            'price_currency_rate' => 3.5,
            'price_tax_mode' => 'exclusive',
            'price_vat_rate' => 20.0,
            'price_byn' => 42.0,
        ], $source->priceSnapshot(10));
    }

    public function test_foreign_price_without_rate_remains_unknown(): void
    {
        $source = new IntegrationSource([
            'price_currency' => 'EUR',
            'price_currency_rate' => null,
            'settings' => ['price_tax_mode' => 'inclusive', 'vat_rate' => 20],
        ]);

        $this->assertNull($source->normalizePriceToByn(10));
        $this->assertSame('EUR · курс не задан · Передаётся с НДС', $source->pricingRuleLabel());
    }
}

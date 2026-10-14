<?php

namespace Tests\Unit;

use App\Console\Commands\SyncBelomoPriceListCommand;
use App\Console\Commands\SyncEliconGasMetersCommand;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class BelomoCatalogCompletionTest extends TestCase
{
    public function test_missing_retail_price_is_calculated_from_vat_inclusive_cost_and_margin_floor(): void
    {
        $command = new SyncBelomoPriceListCommand();
        $method = new ReflectionMethod($command, 'safeRetailPrice');

        $this->assertSame(204.87, $method->invoke($command, 153.65, 10.0));
    }

    public function test_minimum_retail_price_adds_vat_and_twenty_percent_markup(): void
    {
        $command = new SyncBelomoPriceListCommand();
        $method = new ReflectionMethod($command, 'minimumRetailPrice');

        $this->assertSame(221.26, $method->invoke($command, 153.65));
    }

    public function test_elicon_channel_cannot_lower_catalog_below_belomo_markup_floor(): void
    {
        $command = new SyncEliconGasMetersCommand();
        $method = new ReflectionMethod($command, 'catalogRetailPrice');

        $this->assertSame(221.26, $method->invoke($command, '1009.00.00.000-00', 165.39));
        $this->assertSame(250.0, $method->invoke($command, '1009.00.00.000-00', 250.0));
    }

    public function test_image_donor_is_selected_only_from_the_same_article_family(): void
    {
        $command = new SyncBelomoPriceListCommand();
        $method = new ReflectionMethod($command, 'bestImageDonor');
        $target = $this->item('8348.00.00.000-06', 'Счетчик газа ультразвуковой КАТА-G4 R-2');
        $wrongFamily = $this->item('8349.00.00.000-06', 'Счетчик газа ультразвуковой СКАТ-G6 RР');
        $sameSeries = $this->item('8348.00.00.000-10', 'Счетчик газа ультразвуковой КАТА-G4 R-3');

        $this->assertSame(
            '8348.00.00.000-10',
            $method->invoke($command, $target, [$wrongFamily, $sameSeries])['row']['article'],
        );
    }

    public function test_retail_markup_uses_the_median_and_distinguishes_meter_execution(): void
    {
        $command = new SyncBelomoPriceListCommand();
        $median = new ReflectionMethod($command, 'median');
        $variant = new ReflectionMethod($command, 'retailVariantKey');

        $this->assertSame(15.0, $median->invoke($command, [10.5, 15.0, 25.0]));
        $this->assertSame(
            '8349|6|rkp',
            $variant->invoke($command, [
                'article' => '8349.00.00.000-07',
                'name' => 'Счетчик газа ультразвуковой СКАТ-G6 RKР',
            ]),
        );
    }

    private function item(string $article, string $name): array
    {
        return [
            'row' => ['article' => $article, 'name' => $name],
            'product' => (object) ['images' => json_encode(['img/example.webp'])],
        ];
    }
}

<?php

namespace Tests\Unit;

use App\Console\Commands\SyncBelomoPriceListCommand;
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

    private function item(string $article, string $name): array
    {
        return [
            'row' => ['article' => $article, 'name' => $name],
            'product' => (object) ['images' => json_encode(['img/example.webp'])],
        ];
    }
}

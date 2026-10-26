<?php

namespace Tests\Unit;

use App\Console\Commands\SyncDealByBrandPricesCommand;
use App\Models\Brand;
use App\Models\Product;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SyncDealByBrandPricesCommandTest extends TestCase
{
    public function test_it_prepares_price_updates_only_for_exact_or_unique_matches(): void
    {
        $vezuviy = $this->product(11, 'VEZ-11', 'vezuviy-11', 1500.50, 'Везувий');
        $tatra = $this->product(22, 'TATRA-22', 'tatra-22', 3200.00, 'Tatra');

        $remote = collect([
            ['id' => 101, 'external_id' => 'kotlov-11', 'sku' => 'OLD-SKU', 'price' => 1400.00],
            ['id' => 202, 'external_id' => '', 'sku' => 'TATRA-22', 'price' => 3100.00],
            ['id' => 303, 'external_id' => '', 'sku' => 'UNRELATED', 'price' => 99.00],
        ]);

        $method = new ReflectionMethod(SyncDealByBrandPricesCommand::class, 'buildPlan');
        $plan = $method->invoke(new SyncDealByBrandPricesCommand(), collect([$vezuviy, $tatra]), $remote);

        $this->assertSame(2, $plan['matched']);
        $this->assertSame(2, $plan['updates']->count());
        $this->assertSame([101, 202], $plan['updates']->pluck('deal_id')->all());
        $this->assertSame([1500.5, 3200.0], $plan['updates']->pluck('new_price')->all());
        $this->assertSame(0, $plan['unmatched']);
    }

    private function product(int $id, string $sku, string $slug, float $price, string $brandName): Product
    {
        $product = new Product([
            'sku' => $sku,
            'slug' => $slug,
            'price' => $price,
        ]);
        $product->id = $id;
        $product->setRelation('brand', new Brand(['name' => $brandName]));

        return $product;
    }
}

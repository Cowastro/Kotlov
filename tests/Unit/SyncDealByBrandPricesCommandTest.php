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
        $vezuviy = $this->product(11, 'VEZ-11', 'vezuviy-11', 'Печь Везувий Легенда', 1500.50, 'Везувий');
        $tatra = $this->product(22, 'TATRA-22', 'tatra-22', 'Котел Tatra 22', 3200.00, 'Tatra');

        $remote = collect([
            ['id' => 101, 'external_id' => 'kotlov-11', 'sku' => 'OLD-SKU', 'price' => 1400.00],
            ['id' => 202, 'external_id' => '', 'sku' => 'OLD-TATRA', 'name' => 'Котел Tatra 22', 'price' => 3100.00],
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

    public function test_it_matches_legacy_tatra_names_by_unique_model(): void
    {
        $boiler = $this->product(31, 'PS-31', 'tatra-kt30-nl1', 'Tatra КТ30-НЛ1 Сталь 6мм левый одноконтурный', 3312.00, 'Татра');
        $stove = $this->product(32, 'PS-32', 'pec-bannaia-tatra-t16-s-bakom-80l', 'Печь банная Tatra T16 с баком 80 л', 1482.00, 'Татра');

        $remote = collect([
            ['id' => 401, 'external_id' => '', 'sku' => 'OLD-31', 'name' => 'Котел длительного горения Татра KT30 NL1', 'price' => 3000.00],
            ['id' => 402, 'external_id' => '', 'sku' => 'OLD-32', 'name' => 'Печь-каменка Татра Т-16 с баком 80 литров', 'price' => 1300.00],
        ]);

        $method = new ReflectionMethod(SyncDealByBrandPricesCommand::class, 'buildPlan');
        $plan = $method->invoke(new SyncDealByBrandPricesCommand(), collect([$boiler, $stove]), $remote);

        $this->assertSame(2, $plan['matched']);
        $this->assertSame([401, 402], $plan['updates']->pluck('deal_id')->all());
        $this->assertSame([3312.0, 1482.0], $plan['updates']->pluck('new_price')->all());
        $this->assertSame(0, $plan['unmatched']);
    }

    public function test_it_extracts_only_safe_numeric_price_fields_for_diagnostics(): void
    {
        $method = new ReflectionMethod(SyncDealByBrandPricesCommand::class, 'priceFields');
        $fields = $method->invoke(new SyncDealByBrandPricesCommand(), [
            'name' => 'Private product name',
            'price' => '2719.50',
            'discount' => ['value' => 5, 'price' => '2590.00'],
            'contact' => ['sale_email' => 'private@example.test'],
        ]);

        $this->assertSame([
            'price' => 2719.5,
            'discount.price' => 2590.0,
        ], $fields);
    }

    private function product(int $id, string $sku, string $slug, string $name, float $price, string $brandName): Product
    {
        $product = new Product([
            'sku' => $sku,
            'slug' => $slug,
            'name' => $name,
            'price' => $price,
        ]);
        $product->id = $id;
        $product->setRelation('brand', new Brand(['name' => $brandName]));
        $product->setRelation('supplierProducts', collect());

        return $product;
    }
}

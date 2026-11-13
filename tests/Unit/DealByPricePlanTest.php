<?php

namespace Tests\Unit;

use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Models\SupplierProduct;
use App\Services\Marketplaces\DealByPricePlan;
use PHPUnit\Framework\TestCase;

class DealByPricePlanTest extends TestCase
{
    public function test_exact_article_matches_but_conflicting_article_and_name_never_write(): void
    {
        $a = $this->product(1, 'A', 'Печь 18 М');
        $b = $this->product(2, 'B', 'Печь 18 без М');
        $rows = (new DealByPricePlan)->build(collect([$a, $b]), collect([
            $this->remote(10, 'A', 'Other name'), $this->remote(11, 'B', 'Печь 18 М'),
        ]));
        $this->assertSame('update', $rows[0]['reason']);
        $this->assertSame('conflicting_identity', $rows[1]['reason']);
    }

    public function test_duplicate_local_articles_and_duplicate_remote_mappings_are_blocked(): void
    {
        $planner = new DealByPricePlan;
        $a = $this->product(1, 'A', 'Печь 18');
        $b = $this->product(2, 'A', 'Печь 24');
        $this->assertSame('conflicting_identity', $planner->build(collect([$a, $b]), collect([$this->remote(10, 'A', 'unknown')]))[0]['reason']);
        $rows = $planner->build(collect([$a]), collect([$this->remote(10, 'A', 'one'), $this->remote(11, 'A', 'two')]));
        $this->assertSame(['duplicate_mapping', 'duplicate_mapping'], $rows->pluck('reason')->all());
    }

    public function test_inactive_archived_zero_prices_discounts_and_foreign_currency_are_blocked(): void
    {
        foreach ([['is_active' => false], ['is_archived' => true], ['price' => 0], ['price' => -10]] as $attributes) {
            $product = $this->product(1, 'A', 'Печь')->forceFill($attributes);
            $this->assertSame('ineligible_local_product', (new DealByPricePlan)->build(collect([$product]), collect([$this->remote(10, 'A', 'Печь')]))[0]['reason']);
        }
        foreach ([['currency' => 'EUR', 'reason' => 'unsupported_currency'], ['currency' => 'BYR', 'reason' => 'unsupported_currency'],
            ['discount' => ['value' => 10], 'reason' => 'remote_discount'], ['price' => 'unknown', 'reason' => 'invalid_remote_price']] as $attributes) {
            $remote = array_replace($this->remote(10, 'A', 'Печь'), $attributes);
            $this->assertSame($attributes['reason'], (new DealByPricePlan)->build(collect([$this->product(1, 'A', 'Печь')]), collect([$remote]))[0]['reason']);
        }
        $foreign = $this->product(1, 'A', 'Печь')->forceFill(['currency' => 'USD']);
        $this->assertSame('unsupported_local_currency', (new DealByPricePlan)->build(collect([$foreign]), collect([$this->remote(10, 'A', 'Печь')]))[0]['reason']);
    }

    public function test_changed_saved_identity_is_blocked_and_no_fuzzy_suffix_match_is_allowed(): void
    {
        $planner = new DealByPricePlan;
        $a = $this->product(1, 'A', 'Печь 18 М');
        $remote = $this->remote(10, 'A', 'Печь 18 М');
        $link = [10 => ['local_id' => 1, 'fingerprint' => $planner->fingerprint($remote), 'local_slug' => $a->slug]];
        $this->assertSame('saved', $planner->build(collect([$a]), collect([$remote]), $link)[0]['method']);
        $this->assertSame('changed_remote_identity', $planner->build(collect([$a]), collect([array_replace($remote, ['name' => 'Печь 24'])]), $link)[0]['reason']);
        $this->assertSame('unmatched', $planner->build(collect([$a]), collect([$this->remote(10, 'B', 'Печь 18')]))[0]['reason']);
    }

    public function test_equal_price_is_idempotent_and_zero_remote_price_can_be_repaired(): void
    {
        $planner = new DealByPricePlan;
        $a = $this->product(1, 'A', 'Печь');
        $this->assertSame('unchanged', $planner->build(collect([$a]), collect([array_replace($this->remote(10, 'A', 'Печь'), ['price' => 1852])]))[0]['reason']);
        $this->assertSame('update', $planner->build(collect([$a]), collect([array_replace($this->remote(10, 'A', 'Печь'), ['price' => 0])]))[0]['reason']);
    }

    public function test_confirmed_supplier_and_integration_articles_match_and_collisions_still_block(): void
    {
        $a = $this->product(1, 'CRM-1', 'Печь 18 М');
        $a->setRelation('supplierProducts', collect([new SupplierProduct(['supplier_article' => 'FACTORY-18M'])]));
        $b = $this->product(2, 'CRM-2', 'Печь 24 М');
        $b->setRelation('integrationProducts', collect([new IntegrationProduct(['external_sku' => 'FACTORY-24M', 'barcode' => '4600000000010'])]));
        $planner = new DealByPricePlan;
        $rows = $planner->build(collect([$a, $b]), collect([
            $this->remote(10, 'FACTORY-18M', 'Other'), $this->remote(11, '4600000000010', 'Other 2'),
        ]));
        $this->assertSame([1, 2], $rows->pluck('local_id')->all());
        $this->assertSame(['update', 'update'], $rows->pluck('reason')->all());
        $b->setRelation('supplierProducts', collect([new SupplierProduct(['supplier_article' => 'FACTORY-18M'])]));
        $this->assertSame('conflicting_identity', $planner->build(collect([$a, $b]), collect([$this->remote(10, 'FACTORY-18M', 'Other')]))[0]['reason']);
    }

    private function product(int $id, string $sku, string $name): Product
    {
        return (new Product)->forceFill(['id' => $id, 'sku' => $sku, 'slug' => 'stove-'.$id, 'name' => $name, 'price' => 1852, 'currency' => 'BYN',
            'is_active' => true, 'is_archived' => false]);
    }

    private function remote(int $id, string $sku, string $name): array
    {
        return ['id' => $id, 'sku' => $sku, 'name' => $name, 'price' => 1494, 'currency' => 'BYN', 'discount' => null];
    }
}

<?php

namespace Tests\Unit;

use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Models\SupplierProduct;
use App\Services\Marketplaces\DealByPricePlan;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DealByPricePlanTest extends TestCase
{
    public function test_reviewed_legacy_offer_uses_current_rrp_but_never_changes_its_identity(): void
    {
        $planner = new DealByPricePlan;
        $card = $this->product(1, 'A', 'Печь')->forceFill(['is_archived' => true]);
        $item = $this->remote(10, 'A', 'Старая карточка');
        $plan = $planner->build(collect([$card]), collect([$item]));
        $approval = [10 => ['local_id' => 1, 'fingerprint' => $planner->fingerprint($item), 'retail_article' => 'A', 'retail_name' => 'Печь ДТ-3']];
        $retail = [['article' => 'A', 'name' => 'Печь ДТ-3', 'price' => 1900]];
        $row = $planner->withReviewedBaniaRetail($plan, collect([$item]), $approval, $retail)[0];
        $this->assertSame('update', $row['reason']);
        $this->assertSame(1900.0, $row['new_price']);
        $this->assertSame('reviewed_bania_rrp', $row['method']);
        foreach ([[], [$retail[0], $retail[0]], [array_replace($retail[0], ['name' => 'Печь ДТ-3С'])],
            [array_replace($retail[0], ['price' => 0])]] as $unsafe) {
            $this->assertSame('ineligible_local_product', $planner->withReviewedBaniaRetail($plan, collect([$item]), $approval, $unsafe)[0]['reason']);
        }
        foreach ([['sku' => 'B'], ['name' => 'Другая печь'], ['currency' => 'EUR'], ['discount' => ['value' => 10]]] as $change) {
            $changed = array_replace($item, $change);
            $this->assertSame('ineligible_local_product', $planner->withReviewedBaniaRetail($plan, collect([$changed]), $approval, $retail)[0]['reason']);
        }
        $approval[10]['local_id'] = 2;
        $this->assertSame('ineligible_local_product', $planner->withReviewedBaniaRetail($plan, collect([$item]), $approval, $retail)[0]['reason']);
    }

    public function test_reviewed_duplicate_models_keep_separate_rrps_and_duplicate_remote_ids_still_block(): void
    {
        $planner = new DealByPricePlan;
        $card = $this->product(1, 'CRM', 'Печь');
        $card->setRelation('supplierProducts', collect([new SupplierProduct(['supplier_article' => 'A']), new SupplierProduct(['supplier_article' => 'B'])]));
        $remote = collect([$this->remote(10, 'A', 'Печь ДТ-3'), $this->remote(11, 'B', 'Печь ДТ-3С')]);
        $approvals = [];
        foreach ($remote as $item) {
            $approvals[$item['id']] = ['local_id' => 1, 'fingerprint' => $planner->fingerprint($item), 'retail_article' => $item['sku'], 'retail_name' => $item['name']];
        }
        $retail = [['article' => 'A', 'name' => 'Печь ДТ-3', 'price' => 914], ['article' => 'B', 'name' => 'Печь ДТ-3С', 'price' => 953]];
        $rows = $planner->withReviewedBaniaRetail($planner->build(collect([$card]), $remote), $remote, $approvals, $retail);
        $this->assertSame([914.0, 953.0], $rows->pluck('new_price')->all());
        $duplicates = collect([$remote[0], $remote[0]]);
        $rows = $planner->withReviewedBaniaRetail($planner->build(collect([$card]), $duplicates), $duplicates, $approvals, $retail);
        $this->assertSame(['duplicate_mapping', 'duplicate_mapping'], $rows->pluck('reason')->all());
    }

    public function test_only_strictly_expired_promotions_allow_price_sync(): void
    {
        CarbonImmutable::setTestNow('2026-11-16 12:00:00');
        try {
            $planner = new DealByPricePlan;
            foreach (['15.11.2026' => 'update', '16.11.2026' => 'remote_discount',
                '17.11.2026' => 'remote_discount', '31.02.2025' => 'remote_discount', '' => 'remote_discount'] as $end => $expected) {
                $item = array_replace($this->remote(10, 'A', 'Печь'), ['discount' => ['value' => 10, 'date_end' => $end]]);
                $this->assertSame($expected, $planner->build(collect([$this->product(1, 'A', 'Печь')]), collect([$item]))[0]['reason']);
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

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

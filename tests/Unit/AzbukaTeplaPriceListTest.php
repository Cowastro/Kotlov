<?php

namespace Tests\Unit;

use App\Services\Suppliers\AzbukaTeplaPriceList;
use PHPUnit\Framework\TestCase;

class AzbukaTeplaPriceListTest extends TestCase
{
    public function test_normalized_snapshot_contains_only_unambiguous_price_pairs(): void
    {
        $payload = (new AzbukaTeplaPriceList)->load(
            dirname(__DIR__, 2).'/resources/data/suppliers/azbuka-tepla-2026-10-21.json'
        );

        $this->assertSame(260, $payload['retail_rows']);
        $this->assertSame(220, $payload['matched_rows']);
        $this->assertSame(40, $payload['review_rows']);
        $this->assertCount(220, $payload['rows']);
        $this->assertCount(40, $payload['review']);
        $this->assertCount(220, array_unique(array_column($payload['rows'], 'article_key')));

        $skat14 = collect($payload['rows'])->firstWhere('article_key', 'protherm14ckat');
        $this->assertNotNull($skat14);
        $this->assertSame(3550.0, $skat14['retail_byn']);
        $this->assertSame(2911.0, $skat14['purchase_byn']);

        foreach ($payload['rows'] as $row) {
            $this->assertGreaterThan(0, $row['purchase_byn']);
            $this->assertGreaterThan($row['purchase_byn'], $row['retail_byn']);
        }
    }
}

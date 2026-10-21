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
        $this->assertSame(219, $payload['matched_rows']);
        $this->assertSame(41, $payload['review_rows']);
        $this->assertCount(219, $payload['rows']);
        $this->assertCount(41, $payload['review']);
        $this->assertCount(219, array_unique(array_column($payload['rows'], 'article_key')));

        foreach ($payload['rows'] as $row) {
            $this->assertGreaterThan(0, $row['purchase_byn']);
            $this->assertGreaterThan($row['purchase_byn'], $row['retail_byn']);
        }
    }
}
